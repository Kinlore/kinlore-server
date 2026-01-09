<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add throttling with Redis
        $middleware->throttleWithRedis();

        // run CORS before everything else
        $middleware->prepend(HandleCors::class);
        
        // Add security headers
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Add Sanctum middleware for API routes
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Add throttling
        $middleware->api('throttle:api');

        // Alias for auth:sanctum
        $middleware->alias([
            'auth.sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Authorize resource operations automatically
        $middleware->alias([
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->expectsJson();
        });

        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::validation($e->errors());
        });

        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error($e->getMessage() ?: 'Unauthenticated', 401);
        });

        $exceptions->renderable(function (AuthorizationException $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error($e->getMessage() ?: 'Forbidden', 403);
        });

        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error('Resource not found', 404);
        });

        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error('Route not found', 404);
        });

        $exceptions->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error('Method not allowed', 405);
        });

        $exceptions->renderable(function (HttpExceptionInterface $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            $message = $e->getMessage() ?: 'Request failed';

            return ApiResponse::error($message, $e->getStatusCode());
        });

        $exceptions->renderable(function (Throwable $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            $extra = config('app.debug') ? ['error' => $e->getMessage()] : [];

            return ApiResponse::error('Server error', 500, [], $extra);
        });
    })->create();
