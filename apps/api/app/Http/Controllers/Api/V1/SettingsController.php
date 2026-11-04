<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreFaviconRequest;
use App\Http\Requests\Settings\StoreLogoRequest;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Resources\BrandingResource;
use App\Http\Resources\CompanySettingResource;
use App\Http\Responses\ApiResponse;
use App\Models\CompanySetting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function branding(): JsonResponse
    {
        return ApiResponse::data(new BrandingResource(CompanySetting::current()));
    }

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
        $before = $this->snapshot($settings);

        $settings->fill($request->validated());
        // Currency is locked to BDT for Meridian.
        $settings->currency = 'BDT';
        $settings->save();

        $this->auditIfChanged($settings, $before, $auditLogger, $request);

        return ApiResponse::data(new CompanySettingResource($settings->refresh()));
    }

    public function storeLogo(StoreLogoRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        if (! $request->user()->can('settings.manage')) {
            return $this->forbidden('You do not have permission to update company settings.');
        }

        return $this->replaceAsset($request, $auditLogger, 'logo_path', $request->file('image'));
    }

    public function destroyLogo(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        if (! $request->user()->can('settings.manage')) {
            return $this->forbidden('You do not have permission to update company settings.');
        }

        return $this->clearAsset($request, $auditLogger, 'logo_path');
    }

    public function storeFavicon(StoreFaviconRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        if (! $request->user()->can('settings.manage')) {
            return $this->forbidden('You do not have permission to update company settings.');
        }

        return $this->replaceAsset($request, $auditLogger, 'favicon_path', $request->file('image'));
    }

    public function destroyFavicon(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        if (! $request->user()->can('settings.manage')) {
            return $this->forbidden('You do not have permission to update company settings.');
        }

        return $this->clearAsset($request, $auditLogger, 'favicon_path');
    }

    private function replaceAsset(Request $request, AuditLogger $auditLogger, string $column, UploadedFile $file): JsonResponse
    {
        $settings = CompanySetting::current();
        $before = $this->snapshot($settings);
        $previous = $settings->{$column};

        $path = $file->store('branding', 'public');
        if (! is_string($path)) {
            throw new \RuntimeException('The branding file could not be stored.');
        }

        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $settings->{$column} = $path;
        $settings->save();

        $this->auditIfChanged($settings, $before, $auditLogger, $request);

        return ApiResponse::data(new CompanySettingResource($settings->refresh()));
    }

    private function clearAsset(Request $request, AuditLogger $auditLogger, string $column): JsonResponse
    {
        $settings = CompanySetting::current();
        $before = $this->snapshot($settings);
        $previous = $settings->{$column};

        if (is_string($previous) && $previous !== '') {
            Storage::disk('public')->delete($previous);
        }

        $settings->{$column} = null;
        $settings->save();

        $this->auditIfChanged($settings, $before, $auditLogger, $request);

        return ApiResponse::data(new CompanySettingResource($settings->refresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CompanySetting $settings): array
    {
        return $settings->only([
            'name',
            'title',
            'logo_path',
            'favicon_path',
            'currency',
            'po_approval_threshold',
            'adjustment_approval_threshold',
            'points_earn_per',
            'point_redeem_value',
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function auditIfChanged(CompanySetting $settings, array $before, AuditLogger $auditLogger, Request $request): void
    {
        $after = $this->snapshot($settings);

        if ($before === $after) {
            return;
        }

        $auditLogger->write(
            'settings.updated',
            $settings,
            $request->user(),
            $before,
            $after,
            $request,
        );
    }
}
