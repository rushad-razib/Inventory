<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AdjustCash;
use App\Enums\CashDirection;
use App\Enums\CashLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\StoreCashMovementRequest;
use App\Http\Resources\CashEntryResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\CashEntry;
use App\Services\CashBalances;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CashEntryController extends Controller
{
    #[QueryParameter('filter[location]', description: 'cash, bank, or drawer', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CashEntry::class);

        $query = CashEntry::query()->with('actor')->orderByDesc('occurred_at');

        $this->whereLike($query, $request, ['type']);
        $this->whereOn($query, $request, 'occurred_at');

        if ($request->filled('filter.location')) {
            try {
                $query->where(
                    'location',
                    CashLocation::fromLabel((string) $request->input('filter.location')),
                );
            } catch (InvalidArgumentException) {
                return ProblemResponse::make(
                    'validation-failed',
                    'Validation Failed',
                    422,
                    'One or more fields failed validation.',
                    ['location' => ['Unknown location.']],
                );
            }
        }

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request)),
            CashEntryResource::class,
        );
    }

    public function balances(CashBalances $balances): JsonResponse
    {
        $this->authorize('viewAny', CashEntry::class);

        return ApiResponse::data($balances->totals());
    }

    public function store(StoreCashMovementRequest $request, AdjustCash $action): JsonResponse
    {
        if (! $request->user()->can('cash.manage')) {
            return $this->forbidden('You do not have permission to adjust cash.');
        }

        $entry = $action(
            $request->user(),
            CashLocation::fromLabel($request->string('location')->toString()),
            CashDirection::fromLabel($request->string('direction')->toString()),
            $request->integer('amount'),
            $request->string('note')->toString(),
            $request,
        );

        return ApiResponse::data(new CashEntryResource($entry), 201);
    }
}
