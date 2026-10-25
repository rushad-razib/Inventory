<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Http\Requests\Warehouses\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view') && ! $request->user()->can('settings.manage')) {
            return $this->forbidden('You do not have permission to list warehouses.');
        }

        $paginator = Warehouse::query()
            ->orderBy('code')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, WarehouseResource::class);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        if (! $request->user()->can('settings.manage')) {
            return $this->forbidden('You do not have permission to create warehouses.');
        }

        $warehouse = Warehouse::query()->create($request->validated());

        return ApiResponse::data(new WarehouseResource($warehouse), 201);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        if (! $request->user()->can('settings.manage')) {
            return $this->forbidden('You do not have permission to update warehouses.');
        }

        $warehouse->update($request->validated());

        return ApiResponse::data(new WarehouseResource($warehouse->refresh()));
    }
}
