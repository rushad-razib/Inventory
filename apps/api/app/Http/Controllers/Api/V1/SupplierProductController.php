<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierProducts\StoreSupplierProductRequest;
use App\Http\Requests\SupplierProducts\UpdateSupplierProductRequest;
use App\Http\Resources\SupplierProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierProductController extends Controller
{
    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        if (! $request->user()->can('suppliers.manage') && ! $request->user()->can('inventory.view')) {
            return $this->forbidden('You do not have permission to list supplier products.');
        }

        $paginator = $supplier->supplierProducts()
            ->orderBy('created_at')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, SupplierProductResource::class);
    }

    public function store(StoreSupplierProductRequest $request, Supplier $supplier): JsonResponse
    {
        if (! $request->user()->can('suppliers.manage')) {
            return $this->forbidden('You do not have permission to create supplier products.');
        }

        $product = $supplier->supplierProducts()->create($request->validated());

        return ApiResponse::data(new SupplierProductResource($product), 201);
    }

    public function update(UpdateSupplierProductRequest $request, SupplierProduct $supplierProduct): JsonResponse
    {
        if (! $request->user()->can('suppliers.manage')) {
            return $this->forbidden('You do not have permission to update supplier products.');
        }

        $supplierProduct->update($request->validated());

        return ApiResponse::data(new SupplierProductResource($supplierProduct->refresh()));
    }
}
