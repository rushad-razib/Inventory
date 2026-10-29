<?php

namespace App\Actions;

use App\Enums\CustomerReturnStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\CustomerReturnResource;
use App\Models\CustomerReturn;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DecideCustomerReturn
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $actor, CustomerReturn $return, bool $approve, ?Request $request): array
    {
        $decided = DB::transaction(function () use ($actor, $return, $approve, $request): CustomerReturn {
            $locked = CustomerReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CustomerReturnStatus::PendingApproval) {
                throw new LedgerException('invalid-state', 'Only a pending customer return can be decided.');
            }

            if ($locked->requester_id === $actor->id) {
                throw new LedgerException('forbidden', 'You cannot approve your own customer return.', 403);
            }

            $locked->approver_id = $actor->id;
            $locked->status = $approve ? CustomerReturnStatus::Approved : CustomerReturnStatus::Rejected;
            $locked->save();

            $this->audit->write(
                $approve ? 'customer_return.approved' : 'customer_return.rejected',
                $locked,
                $actor,
                ['status' => 'pending_approval'],
                ['status' => $locked->status->label()],
                $request,
            );

            return $locked;
        });

        $decided->load(CustomerReturn::apiRelations());

        return CustomerReturnResource::make($decided)->resolve($request);
    }
}
