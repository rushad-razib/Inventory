<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CashDirection;
use App\Enums\CashLocation;
use App\Http\Controllers\Controller;
use App\Http\Resources\CashEntryResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\CashEntry;
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

    public function balances(): JsonResponse
    {
        $this->authorize('viewAny', CashEntry::class);

        $totals = [
            CashLocation::Till->label() => 0,
            CashLocation::Bank->label() => 0,
        ];

        $rows = CashEntry::query()
            ->selectRaw('location, direction, SUM(amount) as total')
            ->groupBy('location', 'direction')
            ->get();

        foreach ($rows as $row) {
            $location = $row->location instanceof CashLocation
                ? $row->location
                : CashLocation::from((int) $row->location);
            $direction = $row->direction instanceof CashDirection
                ? $row->direction
                : CashDirection::from((int) $row->direction);
            $sign = $direction === CashDirection::In ? 1 : -1;
            $totals[$location->label()] += $sign * (int) $row->total;
        }

        return ApiResponse::data($totals);
    }
}
