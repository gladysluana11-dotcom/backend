<?php

use App\Http\Middleware\ControlarInactividadSpa;
use App\Http\Middleware\EstablecerEquipoDePermisos;
use App\Http\Middleware\RequiereCambioContrasena;
use App\Http\Middleware\VerificarUsuarioActivo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
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
        $middleware->alias([
            'equipo.permisos' => EstablecerEquipoDePermisos::class,
            'sesion.inactiva' => ControlarInactividadSpa::class,
            'contrasena.obligatoria' => RequiereCambioContrasena::class,
            'usuario.activo' => VerificarUsuarioActivo::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'data' => null,
                'errors' => $exception->errors(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Se requiere autenticación.',
                'data' => null,
                'errors' => null,
                'code' => 'UNAUTHENTICATED',
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'No tienes permiso para realizar esta acción.',
                'data' => null,
                'errors' => null,
                'code' => 'FORBIDDEN',
            ], 403);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            [$message, $code] = match ($status) {
                404 => ['No se encontró el recurso solicitado.', 'NOT_FOUND'],
                429 => ['Se alcanzó el límite de solicitudes. Intenta nuevamente más tarde.', 'TOO_MANY_REQUESTS'],
                422 => ['Los datos enviados no son válidos.', 'VALIDATION_ERROR'],
                default => $status >= 500
                    ? ['Ocurrió un error interno. Intenta nuevamente más tarde.', 'INTERNAL_SERVER_ERROR']
                    : ['No se pudo completar la solicitud.', 'HTTP_ERROR'],
            };

            return response()->json([
                'message' => $message,
                'data' => null,
                'errors' => null,
                'code' => $code,
            ], $status);
        });
    })->create();
