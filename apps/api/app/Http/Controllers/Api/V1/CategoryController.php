<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Services\DeletionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()->orderBy('name');
        $this->whereLike($query, $request, ['name']);

        $paginator = $query->paginate($this->perPage($request));

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

    public function destroy(Request $request, Category $category, DeletionGuard $guard): JsonResponse
    {
        if (! $request->user()->can('catalog.manage')) {
            return $this->forbidden('You do not have permission to delete categories.');
        }

        if ($detail = $guard->category($category)) {
            return $this->conflict($detail);
        }

        $category->delete();

        return ApiResponse::data(null);
    }
}
