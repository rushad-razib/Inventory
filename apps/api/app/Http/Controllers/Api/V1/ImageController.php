<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Images\UpdateImageRequest;
use App\Http\Resources\ImageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Image;
use App\Models\Product;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function update(UpdateImageRequest $request, Image $image, ImageService $images): JsonResponse
    {
        if ($response = $this->authorizeParent($request, $image)) {
            return $response;
        }

        $updated = $images->reorder($image, $request->integer('sort_order'));

        return ApiResponse::data(new ImageResource($updated));
    }

    public function destroy(Request $request, Image $image, ImageService $images): JsonResponse
    {
        if ($response = $this->authorizeParent($request, $image)) {
            return $response;
        }

        $images->delete($image);

        return ApiResponse::data(null);
    }

    private function authorizeParent(Request $request, Image $image): ?JsonResponse
    {
        $imageable = $image->imageable;

        if ($imageable instanceof Product) {
            if (! $request->user()->can('catalog.manage')) {
                return $this->forbidden('You do not have permission to manage product images.');
            }

            return null;
        }

        if ($imageable instanceof User) {
            if (! $request->user()->can('users.manage')) {
                return $this->forbidden('You do not have permission to manage user images.');
            }

            return null;
        }

        return $this->forbidden('You do not have permission to manage this image.');
    }
}
