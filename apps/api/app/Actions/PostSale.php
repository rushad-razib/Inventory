<?php

namespace App\Actions;

use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Enums\LotOwnership;
use App\Enums\MovementType;
use App\Enums\PointEntryType;
use App\Enums\SaleStatus;
use App\Enums\SupplierBillStatus;
use App\Enums\TenderType;
use App\Exceptions\LedgerException;
use App\Http\Resources\SaleResource;
use App\Models\CashEntry;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdempotencyStore;
use App\Services\PointLedger;
use App\Services\StockLedger;
use App\Support\LargestRemainder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class PostSale
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly PointLedger $points,
        private readonly IdempotencyStore $idempotency,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{warehouse_id: string, customer_id: string|null, lines: list<array{product_id: string, quantity: int}>, tenders: list<array{type: string, amount: int}>}  $input
     * @return array{status: int, data: array<string, mixed>}
     */
    public function __invoke(User $actor, array $input, string $key, string $hash, ?Request $request): array
    {
        return $this->idempotency->once(
            $actor,
            'sale.create',
            $key,
            $hash,
            function () use ($actor, $input, $key, $request) {
                $sale = $this->post($actor, $input, $key);

                $this->audit->write('sale.posted', $sale, $actor, null, [
                    'number' => $sale->number,
                    'subtotal' => $sale->subtotal,
                    'points_redeemed' => $sale->points_redeemed,
                ], $request);

                return ['status' => 201, 'data' => $this->payload($sale)];
            },
        );
    }

    /**
     * @param  array{warehouse_id: string, customer_id: string|null, lines: list<array{product_id: string, quantity: int}>, tenders: list<array{type: string, amount: int}>}  $input
     */
    private function post(User $actor, array $input, string $key): Sale
    {
        $warehouseId = $input['warehouse_id'];
        $this->assertPin($actor, $warehouseId);

        $settings = CompanySetting::current();
        $customer = $this->lockCustomer($input['customer_id']);
        $quoted = $this->quoteLines($input['lines']);
        $subtotal = (int) $quoted->sum('shelf_total');
        $tenders = $this->tenders($input['tenders'], $customer, $subtotal, $settings->point_redeem_value);
        $pointsRedeemed = $tenders['points'];
        $cashAndCard = $tenders['cash'] + $tenders['card'];
        $pointsEarned = $customer === null || $settings->points_earn_per <= 0
            ? 0
            : intdiv($cashAndCard, $settings->points_earn_per);

        $sale = Sale::query()->create([
            'number' => Sale::nextNumber(),
            'warehouse_id' => $warehouseId,
            'cashier_id' => $actor->id,
            'customer_id' => $customer?->id,
            'status' => SaleStatus::Completed,
            'subtotal' => $subtotal,
            'points_redeemed' => $pointsRedeemed,
            'cash_and_card_paid' => $cashAndCard,
            'total' => $subtotal,
        ]);

        $lines = $quoted->map(function (array $quote) use ($sale): SaleLine {
            return $sale->lines()->create([
                'product_id' => $quote['product_id'],
                'quantity' => $quote['quantity'],
                'unit_price' => $quote['unit_price'],
                'shelf_total' => $quote['shelf_total'],
                'points_redeemed' => 0,
                'cash_paid' => $quote['shelf_total'],
                'points_earned' => 0,
                'quantity_returned' => 0,
            ]);
        });

        $this->splitPoints($lines, $pointsRedeemed, $pointsEarned, $settings->point_redeem_value);

        foreach ($lines as $line) {
            $this->allocate($sale, $line, $warehouseId, $actor, $key);
        }

        if ($tenders['cash'] > 0) {
            $this->cash($sale, $actor, CashLocation::Till, CashDirection::In, CashEntryType::SaleCashIn, $tenders['cash']);
        }

        if ($tenders['card'] > 0) {
            $this->cash($sale, $actor, CashLocation::Bank, CashDirection::In, CashEntryType::SaleCardIn, $tenders['card']);
        }

        foreach ($tenders['rows'] as $row) {
            $sale->tenders()->create($row);
        }

        if ($customer !== null) {
            $this->points->post($customer, PointEntryType::Redeem, -$pointsRedeemed, $sale, $actor);
            $this->points->post($customer, PointEntryType::Earn, $pointsEarned, $sale, $actor);
        }

        return $sale;
    }

    /**
     * @param  list<array{product_id: string, quantity: int}>  $lines
     * @return Collection<int, array{product_id: string, quantity: int, unit_price: int, shelf_total: int}>
     */
    private function quoteLines(array $lines): Collection
    {
        $sorted = collect($lines)->sortBy('product_id')->values();

        return $sorted->map(function (array $line): array {
            $product = Product::query()->whereKey($line['product_id'])->first();

            if ($product === null || ! $product->is_active) {
                throw new LedgerException('invalid-state', 'This product is not active.', 422);
            }

            if (! $product->track_stock) {
                throw new LedgerException('untracked-product', 'This product does not track stock.', 422);
            }

            $quantity = (int) $line['quantity'];

            return [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->sell_price,
                'shelf_total' => $product->sell_price * $quantity,
            ];
        });
    }

    /**
     * @param  list<array{type: string, amount: int}>  $tenders
     * @return array{points: int, cash: int, card: int, rows: list<array{type: TenderType, amount: int}>}
     */
    private function tenders(array $tenders, ?Customer $customer, int $subtotal, int $redeemValue): array
    {
        $points = 0;
        $cash = 0;
        $card = 0;
        $seen = [];
        $rows = [];

        foreach ($tenders as $tender) {
            $type = TenderType::fromLabel($tender['type']);

            if (isset($seen[$type->value])) {
                throw new LedgerException('invalid-state', 'Each tender type can be used once.', 422);
            }

            $seen[$type->value] = true;
            $amount = (int) $tender['amount'];
            $rows[] = ['type' => $type, 'amount' => $amount];

            if ($type === TenderType::Points) {
                $points += $amount;
            } elseif ($type === TenderType::Cash) {
                $cash += $amount;
            } else {
                $card += $amount;
            }
        }

        if ($points > 0 && $customer === null) {
            throw new LedgerException('invalid-state', 'A walk-in sale cannot spend points.', 422);
        }

        $pointValue = $points * $redeemValue;

        if ($customer !== null && $pointValue > $customer->points_balance) {
            throw new LedgerException('invalid-state', 'Points exceed the customer balance.', 422);
        }

        if ($pointValue > $subtotal) {
            throw new LedgerException('invalid-state', 'Points exceed the order total.', 422);
        }

        if ($cash + $card + $pointValue !== $subtotal) {
            throw new LedgerException('invalid-state', 'Tenders must equal the shelf total.', 422);
        }

        return [
            'points' => $points,
            'cash' => $cash,
            'card' => $card,
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, SaleLine>  $lines
     */
    private function splitPoints(Collection $lines, int $pointsRedeemed, int $pointsEarned, int $redeemValue): void
    {
        $redeemed = LargestRemainder::split(
            $lines->map(fn (SaleLine $line): array => ['id' => $line->id, 'weight' => $line->shelf_total])->all(),
            $pointsRedeemed,
        );

        foreach ($lines as $line) {
            $line->points_redeemed = $redeemed[$line->id] ?? 0;
            $cashPaid = $line->shelf_total - ($line->points_redeemed * $redeemValue);

            if ($cashPaid < 0) {
                throw new LedgerException('invalid-state', 'Points exceed a line shelf total.', 422);
            }

            $line->cash_paid = $cashPaid;
            $line->save();
        }

        $earned = LargestRemainder::split(
            $lines->map(fn (SaleLine $line): array => ['id' => $line->id, 'weight' => $line->cash_paid])->all(),
            $pointsEarned,
        );

        foreach ($lines as $line) {
            $line->points_earned = $earned[$line->id] ?? 0;
            $line->save();
        }
    }

    private function allocate(Sale $sale, SaleLine $line, string $warehouseId, User $actor, string $key): void
    {
        $slices = $this->ledger->consumeOnHand(
            $warehouseId,
            $line->product_id,
            $line->quantity,
            MovementType::Sale,
            $sale,
            $actor,
            $key,
            false,
            false,
        );

        foreach ($slices as $slice) {
            $lot = $slice['lot'];
            $allocation = $line->allocations()->create([
                'stock_lot_id' => $lot->id,
                'quantity' => $slice['quantity'],
                'unit_cost' => $lot->unit_cost,
                'ownership' => $lot->ownership,
            ]);

            if ($lot->ownership !== LotOwnership::Consignment) {
                continue;
            }

            if ($lot->supplier_id === null) {
                throw new LedgerException('invalid-state', 'A consignment lot needs a supplier.');
            }

            $supplier = Supplier::query()->findOrFail($lot->supplier_id);

            SupplierBill::query()->create([
                'supplier_id' => $supplier->id,
                'source_type' => $allocation->getMorphClass(),
                'source_id' => $allocation->id,
                'status' => SupplierBillStatus::Open,
                'amount' => $lot->unit_cost * $slice['quantity'],
                'amount_paid' => 0,
                'credited_amount' => 0,
                'due_on' => now()->addDays($supplier->payment_terms_days)->toDateString(),
            ]);
        }
    }

    private function lockCustomer(?string $customerId): ?Customer
    {
        if ($customerId === null) {
            return null;
        }

        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->first();

        if ($customer === null) {
            throw new LedgerException('invalid-state', 'That customer does not exist.', 422);
        }

        return $customer;
    }

    private function assertPin(User $actor, string $warehouseId): void
    {
        if ($actor->warehouse_id !== null && $actor->warehouse_id !== $warehouseId) {
            throw new LedgerException('forbidden', 'You can only sell from your pinned warehouse.', 403);
        }
    }

    private function cash(
        Sale $sale,
        User $actor,
        CashLocation $location,
        CashDirection $direction,
        CashEntryType $type,
        int $amount,
    ): void {
        CashEntry::query()->create([
            'location' => $location,
            'direction' => $direction,
            'type' => $type,
            'amount' => $amount,
            'source_type' => $sale->getMorphClass(),
            'source_id' => $sale->id,
            'actor_id' => $actor->id,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Sale $sale): array
    {
        $sale->load(Sale::apiRelations());

        return SaleResource::make($sale)->resolve(request());
    }
}
