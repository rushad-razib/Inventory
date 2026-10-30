<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesReportWindow;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\DashboardQuery;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesReportWindow;

    public function __construct(private readonly DashboardQuery $dashboard) {}

    #[QueryParameter('from', description: 'Start date YYYY-MM-DD. Defaults to six days ago.', type: 'string')]
    #[QueryParameter('to', description: 'End date YYYY-MM-DD. Defaults to today.', type: 'string')]
    #[QueryParameter('group', description: 'product or category', type: 'string')]
    public function show(Request $request): JsonResponse
    {
        if (! $request->user()->can('reports.view')) {
            return $this->forbidden('You do not have permission to view the dashboard.');
        }

        $window = $this->reportWindow($request);

        if ($window instanceof JsonResponse) {
            return $window;
        }

        return ApiResponse::data($this->dashboard->payload($window), 200, $window->meta());
    }
}
