<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'cors' => \App\Http\Middleware\Cors::class,
            'auth.active' => \App\Http\Middleware\Authenticate::class,
            'rate.limit' => \App\Http\Middleware\ApiRateLimit::class,
            'cache.api' => \App\Http\Middleware\CacheResponse::class,
            'audit' => \App\Http\Middleware\AuditLog::class,
        ]);
        $middleware->prepend([
            \App\Http\Middleware\Cors::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\GlobalRateLimit::class,
        ]);
        $middleware->redirectGuestsTo(fn ($request) => $request->expectsJson() ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        if (!config('app.debug')) {
            $exceptions->dontReportDetails();
            $exceptions->shouldntRender();
        }

        $exceptions->renderable(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($e instanceof \Illuminate\Auth\AuthenticationException ||
                    $e instanceof \Illuminate\Validation\ValidationException) {
                    return null;
                }

                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return response()->json([
                    'message' => match ($status) {
                        404 => 'Recurso no encontrado',
                        403 => 'No tienes permiso para realizar esta acción',
                        401 => 'No autenticado',
                        422 => 'Datos de entrada inválidos',
                        429 => 'Demasiadas solicitudes. Intenta más tarde.',
                        default => 'Error interno del servidor',
                    },
                ], $status);
            }
        });
    })->create();
