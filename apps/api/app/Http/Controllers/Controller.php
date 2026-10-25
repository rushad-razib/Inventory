<?php

namespace App\Http\Controllers;

use App\Http\Responses\ProblemResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function forbidden(string $detail = 'You do not have permission to perform this action.'): JsonResponse
    {
        return ProblemResponse::make(
            'forbidden',
            'Forbidden',
            403,
            $detail,
        );
    }

    protected function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->integer('per_page', 25)));
    }
}
