<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CashLocation;
use App\Http\Controllers\Controller;
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
    #[QueryParameter('filter[location]', description: 'till or bank', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CashEntry::class);

        $query = CashEntry::query()->with('actor')->orderByDesc('occurred_at');

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
}
