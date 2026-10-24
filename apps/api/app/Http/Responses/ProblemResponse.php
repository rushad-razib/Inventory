<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ProblemResponse
{
    public static function make(
        string $type,
        string $title,
        int $status,
        string $detail,
        array $errors = [],
    ): JsonResponse {
        $payload = [
            'type' => '/problems/'.$type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
