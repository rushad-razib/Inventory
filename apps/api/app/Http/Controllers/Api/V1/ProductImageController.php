<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Images\StoreImageRequest;
use App\Http\Resources\ImageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function index(Request $request, Product $product, ImageService $images): JsonResponse
    {
        if (! $request->user()->can('inventory.view') && ! $request->user()->can('catalog.manage')) {
            return $this->forbidden('You do not have permission to list product images.');
        }

        return ApiResponse::data(
            ImageResource::collection($images->list($product))->resolve(),
        );
    }

    public function store(StoreImageRequest $request, Product $product, ImageService $images): JsonResponse
    {
        if (! $request->user()->can('catalog.manage')) {
            return $this->forbidden('You do not have permission to upload product images.');
        }

        $image = $images->attach(
            $product,
            $request->file('image'),
            $request->has('sort_order') ? $request->integer('sort_order') : null,
        );

        return ApiResponse::data(new ImageResource($image), 201);
    }
}
