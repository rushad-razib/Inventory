<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginator = Category::query()
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, CategoryResource::class);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        if (! $request->user()->can('catalog.manage')) {
            return $this->forbidden('You do not have permission to create categories.');
        }

        $category = Category::query()->create($request->validated());

        return ApiResponse::data(new CategoryResource($category), 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        if (! $request->user()->can('catalog.manage')) {
            return $this->forbidden('You do not have permission to update categories.');
        }

        $category->update($request->validated());

        return ApiResponse::data(new CategoryResource($category->refresh()));
    }
}
