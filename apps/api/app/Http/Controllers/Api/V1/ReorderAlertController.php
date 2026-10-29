<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListReorderAlerts;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReorderAlertResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ReorderAlertController extends Controller
{
    public function index(Request $request, ListReorderAlerts $action): JsonResponse
    {
        if (! $request->user()->can('purchasing.create')) {
            return $this->forbidden('You do not have permission to view reorder alerts.');
        }

        $alerts = $action(
            $request->filled('filter.warehouse_id')
                ? (string) $request->input('filter.warehouse_id')
                : null,
        );

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->integer('page', 1));
        $slice = array_slice($alerts, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator(
            $slice,
            count($alerts),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return ApiResponse::paginated($paginator, ReorderAlertResource::class);
    }
}
