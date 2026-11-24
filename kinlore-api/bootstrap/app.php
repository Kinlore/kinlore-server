<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
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
        // Behind a TLS reverse proxy, trust its X-Forwarded-* headers so signed links use https.
        // Default: private networks (Docker bridge, LAN). Set TRUSTED_PROXIES=* or a list of IPs to change it.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'),
        );

        // There is no login page: a request without a valid token gets 401, never a redirect
        $middleware->redirectGuestsTo(fn (): null => null);

        // run CORS before everything else
        $middleware->prepend(HandleCors::class);

        $middleware->append(SecurityHeaders::class);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api('throttle:api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API paths always answer JSON, also to a browser or curl without an Accept header
        $isApi = fn (Request $request): bool => $request->expectsJson() || $request->is('api/*');

        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $isApi($request));

        // Each renderer returns null outside the API, so Laravel's default page renders instead
        $exceptions->renderable(fn (ValidationException $e, Request $request): ?JsonResponse => $isApi($request)
            ? ApiResponse::validation($e->errors())
            : null);

        $exceptions->renderable(fn (AuthenticationException $e, Request $request): ?JsonResponse => $isApi($request)
            ? ApiResponse::error($e->getMessage() ?: 'Unauthenticated', 401)
            : null);

        $exceptions->renderable(fn (AuthorizationException $e, Request $request): ?JsonResponse => $isApi($request)
            ? ApiResponse::error($e->getMessage() ?: 'Forbidden', 403)
            : null);

        $exceptions->renderable(fn (ModelNotFoundException $e, Request $request): ?JsonResponse => $isApi($request)
            ? ApiResponse::error('Resource not found', 404)
            : null);

        $exceptions->renderable(fn (NotFoundHttpException $e, Request $request): ?JsonResponse => $isApi($request)
            ? ApiResponse::error('Route not found', 404)
            : null);

        $exceptions->renderable(fn (MethodNotAllowedHttpException $e, Request $request): ?JsonResponse => $isApi($request)
            ? ApiResponse::error('Method not allowed', 405)
            : null);

        $exceptions->renderable(fn (HttpExceptionInterface $e, Request $request): ?JsonResponse => $isApi($request)
            ? ApiResponse::error($e->getMessage() ?: 'Request failed', $e->getStatusCode())
            : null);

        $exceptions->renderable(fn (Throwable $e, Request $request): ?JsonResponse => $isApi($request)
            ? ApiResponse::error('Server error', 500, [], config('app.debug') ? ['error' => $e->getMessage()] : [])
            : null);
    })->create();
