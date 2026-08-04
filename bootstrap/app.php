<?php

use App\Http\Middleware\EnsureNotAdmin;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Deliberately NOT stateful. The SPA authenticates with bearer tokens
        // from a cross-origin host (Vercel -> Railway), so the cookie/CSRF path
        // was never exercised — it only widened the attack surface. Moving to
        // cookie auth would also mean supports_credentials in config/cors.php
        // and setting SANCTUM_STATEFUL_DOMAINS.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Previously only /auth/login was throttled; every other endpoint was
        // unlimited. The `api` limiter is defined in AppServiceProvider.
        // EnsurePasswordChanged runs on every API request but only acts on an
        // authenticated user still holding a temporary password, so it is
        // appended globally rather than repeated on each protected group.
        $middleware->api(append: [
            'throttle:api',
            EnsurePasswordChanged::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'not.admin' => EnsureNotAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
        | Every API error leaves through this block in the same envelope the
        | success responses use: { success, message, errors? }. The frontend's
        | getErrorMessage() helper reads `message`, so anything that escapes
        | untranslated surfaces to the user as a generic failure.
        */

        $exceptions->render(fn (ValidationException $e) => response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors(),
        ], 422));

        $exceptions->render(fn (AuthenticationException $e) => response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Please log in.',
        ], 401));

        // Policies use Response::denyWithStatus(), so the intended status code
        // (403 for ownership, 409 for an already-approved grade, 422 for a bad
        // state transition) travels on the exception rather than collapsing to
        // a blanket 403.
        $exceptions->render(fn (AuthorizationException $e) => response()->json([
            'success' => false,
            'message' => $e->getMessage() ?: 'This action is unauthorized.',
        ], $e->status() ?? 403));

        $exceptions->render(fn (UnauthorizedException $e) => response()->json([
            'success' => false,
            'message' => 'You do not have permission to access this resource.',
        ], 403));

        $exceptions->render(fn (ModelNotFoundException $e) => response()->json([
            'success' => false,
            'message' => 'The requested resource was not found.',
        ], 404));

        $exceptions->render(fn (NotFoundHttpException $e) => response()->json([
            'success' => false,
            'message' => 'The requested endpoint was not found.',
        ], 404));

        /*
        | Catch-all. This must stay last and must re-raise anything that already
        | carries an HTTP status: the previous version type-hinted \Throwable
        | with no such check, so every 404, 403 and 429 was rewritten into a 500
        | reading "An unexpected error occurred".
        */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Request could not be completed.',
                ], $e->getStatusCode(), $e->getHeaders());
            }

            if (app()->environment('local', 'testing')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        });
    })->create();
