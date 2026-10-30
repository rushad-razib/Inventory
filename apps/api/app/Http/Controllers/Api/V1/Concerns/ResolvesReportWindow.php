<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Responses\ProblemResponse;
use App\Services\ReportWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

trait ResolvesReportWindow
{
    protected function reportWindow(Request $request): ReportWindow|JsonResponse
    {
        try {
            return ReportWindow::fromRequest($request);
        } catch (InvalidArgumentException $exception) {
            $field = $exception->getMessage();
            $message = match ($field) {
                'group' => 'Choose product or category.',
                'to' => 'Enter an end date as YYYY-MM-DD.',
                default => 'Enter a start date as YYYY-MM-DD on or before the end date.',
            };

            return ProblemResponse::make(
                'validation-failed',
                'Validation Failed',
                422,
                'One or more fields failed validation.',
                [$field => [$message]],
            );
        }
    }
}
