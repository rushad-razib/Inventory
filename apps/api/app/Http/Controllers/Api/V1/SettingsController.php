<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Resources\CompanySettingResource;
use App\Http\Responses\ApiResponse;
use App\Models\CompanySetting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data(new CompanySettingResource(CompanySetting::current()));
    }

    public function update(UpdateSettingsRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        if (! $request->user()->can('settings.manage')) {
            return $this->forbidden('You do not have permission to update company settings.');
        }

        $settings = CompanySetting::current();
        $before = $settings->only([
            'name',
            'currency',
            'po_approval_threshold',
            'adjustment_approval_threshold',
            'points_earn_per',
            'point_redeem_value',
        ]);

        $settings->fill($request->validated());
        // Currency is locked to BDT for Meridian.
        $settings->currency = 'BDT';
        $settings->save();

        $after = $settings->only([
            'name',
            'currency',
            'po_approval_threshold',
            'adjustment_approval_threshold',
            'points_earn_per',
            'point_redeem_value',
        ]);

        if ($before !== $after) {
            $auditLogger->write(
                'settings.updated',
                $settings,
                $request->user(),
                $before,
                $after,
                $request,
            );
        }

        return ApiResponse::data(new CompanySettingResource($settings->refresh()));
    }
}
