<?php

use App\Http\Responses\ProblemResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            // Token-authenticated clients do not need CSRF.
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ProblemResponse::make(
                'validation-failed',
                'Validation Failed',
                422,
                'One or more fields failed validation.',
                $e->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ProblemResponse::make(
                'unauthenticated',
                'Unauthenticated',
                401,
                'Authentication is required.',
            );
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = $e->getStatusCode();
            $title = match ($status) {
                403 => 'Forbidden',
                404 => 'Not Found',
                409 => 'Conflict',
                default => 'Error',
            };

            return ProblemResponse::make(
                match ($status) {
                    403 => 'forbidden',
                    404 => 'not-found',
                    409 => 'conflict',
                    default => 'http-error',
                },
                $title,
                $status,
                $e->getMessage() !== '' ? $e->getMessage() : $title,
            );
        });
    })->create();
