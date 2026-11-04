<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CloseDrawer;
use App\Actions\OpenDrawer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drawers\CloseDrawerRequest;
use App\Http\Requests\Drawers\OpenDrawerRequest;
use App\Http\Resources\DrawerSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\DrawerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrawerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('cash.manage')) {
            return $this->forbidden('You do not have permission to list drawers.');
        }

        return ApiResponse::paginated(
            DrawerSession::query()->orderByDesc('opened_at')->paginate($this->perPage($request)),
            DrawerSessionResource::class,
        );
    }

    public function current(Request $request): JsonResponse
    {
        if (! $request->user()->can('sales.create') && ! $request->user()->can('cash.manage')) {
            return $this->forbidden('You do not have permission to view a drawer.');
        }

        $drawer = DrawerSession::openFor($request->user());

        return ApiResponse::data($drawer === null ? null : new DrawerSessionResource($drawer));
    }

    public function store(OpenDrawerRequest $request, OpenDrawer $action): JsonResponse
    {
        if (! $request->user()->can('sales.create')) {
            return $this->forbidden('You do not have permission to open a drawer.');
        }

        $session = $action(
            $request->user(),
            $request->string('warehouse_id')->toString(),
            $request->integer('opening_balance'),
            $request,
        );

        return ApiResponse::data(new DrawerSessionResource($session), 201);
    }

    public function close(CloseDrawerRequest $request, DrawerSession $drawer, CloseDrawer $action): JsonResponse
    {
        $session = $action(
            $request->user(),
            $drawer,
            $request->integer('counted_amount'),
            $request,
        );

        return ApiResponse::data(new DrawerSessionResource($session));
    }
}
