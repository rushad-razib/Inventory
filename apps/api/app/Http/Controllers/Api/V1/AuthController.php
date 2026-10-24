<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreateTokenRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return ApiResponse::data(new UserResource($user->loadMissing('roles', 'permissions')));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::data(null);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::data(new UserResource($request->user()->loadMissing('roles', 'permissions')));
    }

    public function createToken(CreateTokenRequest $request): JsonResponse
    {
        $abilities = $request->input('abilities');

        if ($abilities === null || $abilities === []) {
            $abilities = $request->user()->getAllPermissions()->pluck('name')->all();
        }

        $token = $request->user()->createToken(
            $request->string('name')->toString(),
            $abilities,
        );

        return ApiResponse::data(
            new PersonalAccessTokenResource($token->accessToken, $token->plainTextToken),
            201,
        );
    }

    public function revokeToken(Request $request, string $id): JsonResponse
    {
        $token = $request->user()->tokens()->whereKey($id)->first();

        if (! $token) {
            return ProblemResponse::make(
                'not-found',
                'Not Found',
                404,
                'The personal access token was not found.',
            );
        }

        $token->delete();

        return ApiResponse::data(null);
    }
}
