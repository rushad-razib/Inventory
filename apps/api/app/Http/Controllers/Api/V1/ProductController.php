<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view') && ! $request->user()->can('catalog.manage')) {
            return $this->forbidden('You do not have permission to list products.');
        }

        $query = Product::query()->orderBy('sku');

        if ($request->filled('filter.sku')) {
            $query->where('sku', $request->input('filter.sku'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        return ApiResponse::paginated($query->paginate($this->perPage($request)), ProductResource::class);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        if (! $request->user()->can('inventory.view') && ! $request->user()->can('catalog.manage')) {
            return $this->forbidden('You do not have permission to view products.');
        }

        return ApiResponse::data(new ProductResource($product));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        if (! $request->user()->can('catalog.manage')) {
            return $this->forbidden('You do not have permission to create products.');
        }

        $product = Product::query()->create($request->validated());

        return ApiResponse::data(new ProductResource($product), 201);
    }

    public function update(UpdateProductRequest $request, Product $product, AuditLogger $auditLogger): JsonResponse
    {
        if (! $request->user()->can('catalog.manage')) {
            return $this->forbidden('You do not have permission to update products.');
        }

        $beforePrice = $product->sell_price;
        $product->update($request->validated());

        if ($request->exists('sell_price') && $beforePrice !== $product->sell_price) {
            $auditLogger->write(
                'product.price_changed',
                $product,
                $request->user(),
                ['sell_price' => $beforePrice],
                ['sell_price' => $product->sell_price],
                $request,
            );
        }

        return ApiResponse::data(new ProductResource($product->refresh()));
    }

    public function lookup(Request $request): JsonResponse
    {
        if (! $request->user()->can('sales.create') && ! $request->user()->can('inventory.view')) {
            return $this->forbidden('You do not have permission to look up products.');
        }

        $barcode = $request->query('barcode');

        if (! is_string($barcode) || $barcode === '') {
            return ApiResponse::data(null);
        }

        $product = Product::query()->where('barcode', $barcode)->first();

        return ApiResponse::data($product ? new ProductResource($product) : null);
    }
}
