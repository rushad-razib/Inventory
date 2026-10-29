<?php

namespace App\Actions;

use App\Enums\CustomerReturnStatus;
use App\Enums\ReturnReason;
use App\Enums\SaleStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\CustomerReturnResource;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\LargestRemainder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RequestCustomerReturn
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{sale_id: string, lines: list<array{sale_line_id: string, quantity: int, reason: string}>}  $input
     * @return array<string, mixed>
     */
    public function __invoke(User $actor, array $input, ?Request $request): array
    {
        $return = DB::transaction(function () use ($actor, $input, $request): CustomerReturn {
            $sale = Sale::query()->whereKey($input['sale_id'])->lockForUpdate()->first();

            if ($sale === null) {
                throw new LedgerException('invalid-state', 'That sale does not exist.', 422);
            }

            if ($sale->status !== SaleStatus::Completed) {
                throw new LedgerException('invalid-state', 'Only a completed sale can be returned.');
            }

            $return = CustomerReturn::query()->create([
                'sale_id' => $sale->id,
                'requester_id' => $actor->id,
                'approver_id' => null,
                'status' => CustomerReturnStatus::PendingApproval,
            ]);

            $lines = SaleLine::query()->where('sale_id', $sale->id)->get()->keyBy('id');

            foreach ($input['lines'] as $requested) {
                $line = $lines->get($requested['sale_line_id']);

                if (! $line instanceof SaleLine) {
                    throw new LedgerException('invalid-state', 'That line is not on this sale.', 422);
                }

                $quantity = (int) $requested['quantity'];
                $reserved = (int) CustomerReturnLine::query()
                    ->where('sale_line_id', $line->id)
                    ->whereHas('customerReturn', function ($query): void {
                        $query->where('status', '!=', CustomerReturnStatus::Rejected);
                    })
                    ->sum('quantity');
                $available = $line->quantity - $reserved;

                if ($quantity > $available) {
                    throw new LedgerException('invalid-state', 'Return quantity exceeds the units still sold.');
                }

                $return->lines()->create([
                    'sale_line_id' => $line->id,
                    'quantity' => $quantity,
                    'reason' => ReturnReason::fromLabel($requested['reason']),
                    'cash_amount' => LargestRemainder::cumulativeSlice($line->cash_paid, $line->quantity, $line->quantity_returned, $quantity),
                    'points_restored' => LargestRemainder::cumulativeSlice($line->points_redeemed, $line->quantity, $line->quantity_returned, $quantity),
                    'points_clawed_back' => LargestRemainder::cumulativeSlice($line->points_earned, $line->quantity, $line->quantity_returned, $quantity),
                ]);
            }

            $this->audit->write('customer_return.requested', $return, $actor, null, [
                'sale_id' => $sale->id,
                'status' => 'pending_approval',
            ], $request);

            return $return;
        });

        $return->load(CustomerReturn::apiRelations());

        return CustomerReturnResource::make($return)->resolve($request);
    }
}
