<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Phase 1 sample protected route. Full company settings arrive in Phase 2.
     */
    public function show(Request $request): JsonResponse
    {
        if (! $request->user()->can('settings.manage')) {
            return ProblemResponse::make(
                'forbidden',
                'Forbidden',
                403,
                'You do not have permission to view company settings.',
            );
        }

        return ApiResponse::data([
            'currency' => 'BDT',
            'points_earn_per' => 100,
            'point_redeem_value' => 1,
            'po_approval_threshold' => 20000,
            'adjustment_approval_threshold' => 5000,
        ]);
    }
}
