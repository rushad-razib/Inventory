<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('users.manage')) {
            return $this->forbidden('You do not have permission to list users.');
        }

        $paginator = User::query()
            ->with(['roles', 'permissions', 'department'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, UserResource::class);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->can('users.manage')) {
            return $this->forbidden('You do not have permission to view users.');
        }

        return ApiResponse::data(new UserResource($user->loadMissing('roles', 'permissions', 'department')));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        if (! $request->user()->can('users.manage')) {
            return $this->forbidden('You do not have permission to create users.');
        }

        $data = $request->safe()->except(['role']);
        $data['is_active'] = $data['is_active'] ?? true;

        $user = User::query()->create($data);
        $user->syncRoles([$request->string('role')->toString()]);

        return ApiResponse::data(
            new UserResource($user->loadMissing('roles', 'permissions', 'department')),
            201,
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if (! $request->user()->can('users.manage')) {
            return $this->forbidden('You do not have permission to update users.');
        }

        $data = $request->safe()->except(['role', 'password']);

        if ($request->filled('password')) {
            $data['password'] = $request->string('password')->toString();
        }

        $user->update($data);

        if ($request->filled('role')) {
            $user->syncRoles([$request->string('role')->toString()]);
        }

        return ApiResponse::data(new UserResource($user->refresh()->loadMissing('roles', 'permissions', 'department')));
    }
}
