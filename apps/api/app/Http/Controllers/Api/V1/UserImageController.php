<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Images\StoreImageRequest;
use App\Http\Resources\ImageResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserImageController extends Controller
{
    public function index(Request $request, User $user, ImageService $images): JsonResponse
    {
        if (! $request->user()->can('users.manage')) {
            return $this->forbidden('You do not have permission to list user images.');
        }

        return ApiResponse::data(
            ImageResource::collection($images->list($user))->resolve(),
        );
    }

    public function store(StoreImageRequest $request, User $user, ImageService $images): JsonResponse
    {
        if (! $request->user()->can('users.manage')) {
            return $this->forbidden('You do not have permission to upload user images.');
        }

        $image = $images->attach(
            $user,
            $request->file('image'),
            $request->has('sort_order') ? $request->integer('sort_order') : null,
        );

        return ApiResponse::data(new ImageResource($image), 201);
    }
}
