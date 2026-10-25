<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SupplierStatus;
use App\Enums\SupplyMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\StoreSupplierRequest;
use App\Http\Requests\Suppliers\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Http\Responses\ApiResponse;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view') && ! $request->user()->can('suppliers.manage')) {
            return $this->forbidden('You do not have permission to list suppliers.');
        }

        $paginator = Supplier::query()
            ->orderBy('code')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, SupplierResource::class);
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        if (! $request->user()->can('inventory.view') && ! $request->user()->can('suppliers.manage')) {
            return $this->forbidden('You do not have permission to view suppliers.');
        }

        return ApiResponse::data(new SupplierResource($supplier));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        if (! $request->user()->can('suppliers.manage')) {
            return $this->forbidden('You do not have permission to create suppliers.');
        }

        $data = $this->enumPayload($request->validated());
        $supplier = Supplier::query()->create($data);

        return ApiResponse::data(new SupplierResource($supplier), 201);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        if (! $request->user()->can('suppliers.manage')) {
            return $this->forbidden('You do not have permission to update suppliers.');
        }

        $supplier->update($this->enumPayload($request->validated()));

        return ApiResponse::data(new SupplierResource($supplier->refresh()));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enumPayload(array $data): array
    {
        if (isset($data['supply_mode'])) {
            $data['supply_mode'] = SupplyMode::fromLabel($data['supply_mode']);
        }

        if (isset($data['status'])) {
            $data['status'] = SupplierStatus::fromLabel($data['status']);
        }

        return $data;
    }
}
