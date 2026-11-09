<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Department;
use App\Services\DeletionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Department::query()->orderBy('name');
        $this->whereLike($query, $request, ['name']);

        $paginator = $query->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, DepartmentResource::class);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        if (! $request->user()->can('users.manage')) {
            return $this->forbidden('You do not have permission to create departments.');
        }

        $department = Department::query()->create($request->validated());

        return ApiResponse::data(new DepartmentResource($department), 201);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        if (! $request->user()->can('users.manage')) {
            return $this->forbidden('You do not have permission to update departments.');
        }

        $department->update($request->validated());

        return ApiResponse::data(new DepartmentResource($department->refresh()));
    }

    public function destroy(Request $request, Department $department, DeletionGuard $guard): JsonResponse
    {
        if (! $request->user()->can('users.manage')) {
            return $this->forbidden('You do not have permission to delete departments.');
        }

        if ($detail = $guard->department($department)) {
            return $this->conflict($detail);
        }

        $department->delete();

        return ApiResponse::data(null);
    }
}
