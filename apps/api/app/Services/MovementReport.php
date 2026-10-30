<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class MovementReport
{
    /**
     * @return Builder<StockMovement>
     */
    public function query(Request $request): Builder
    {
        $query = StockMovement::query()->with(['warehouse', 'product'])->orderByDesc('occurred_at');

        if ($request->filled('filter.warehouse_id')) {
            $query->where('warehouse_id', $request->input('filter.warehouse_id'));
        }

        if ($request->filled('filter.product_id')) {
            $query->where('product_id', $request->input('filter.product_id'));
        }

        if ($request->filled('filter.type')) {
            $query->where('type', MovementType::fromLabel((string) $request->input('filter.type')));
        }

        if ($request->filled('filter.from')) {
            $query->where('occurred_at', '>=', $request->date('filter.from'));
        }

        if ($request->filled('filter.to')) {
            $query->where('occurred_at', '<=', $request->date('filter.to'));
        }

        return $query;
    }

    public function typeIsKnown(Request $request): bool
    {
        if (! $request->filled('filter.type')) {
            return true;
        }

        try {
            MovementType::fromLabel((string) $request->input('filter.type'));
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
