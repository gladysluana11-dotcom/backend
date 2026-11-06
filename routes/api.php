<?php

use App\Http\Controllers\Api\V1\AlumnoController;
use App\Http\Controllers\Api\V1\AuditoriaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ColegioController;
use App\Http\Controllers\Api\V1\PermisoController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => [
        'message' => 'Servicio disponible.',
        'data' => ['status' => 'ok'],
        'errors' => null,
        'code' => 'OK',
    ]);
    Route::get('/publico/colegio', [ColegioController::class, 'publicInfo'])->middleware('throttle:30,1');

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/token', [AuthController::class, 'createToken'])->middleware('throttle:login');
    Route::post('/auth/recuperar-contrasena', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('/auth/restablecer-contrasena', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

    Route::middleware([
        'auth:sanctum',
        'usuario.activo',
        'sesion.inactiva',
        'equipo.permisos',
    ])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('/auth/cambiar-contrasena', [AuthController::class, 'cambiarContrasena'])
            ->name('api.v1.auth.cambiar-contrasena');

        Route::middleware('contrasena.obligatoria')->group(function () {
            Route::get('/auth/me', [AuthController::class, 'me']);

            Route::apiResource('usuarios', UsuarioController::class)
                ->only(['index', 'show', 'store', 'update']);
            Route::post('/usuarios/{usuario}/desactivar', [UsuarioController::class, 'desactivar']);
            Route::post('/usuarios/{usuario}/reactivar', [UsuarioController::class, 'reactivar']);

            Route::get('/roles', [RolController::class, 'index'])->middleware('permission:roles.ver');
            Route::get('/permisos', [PermisoController::class, 'index'])->middleware('permission:permisos.ver');
            Route::get('/auditoria', [AuditoriaController::class, 'index']);

            Route::get('/alumnos/{id}', [AlumnoController::class, 'show'])->middleware('permission:alumnos.ver');
            Route::patch('/alumnos/{id}', [AlumnoController::class, 'update'])->middleware('permission:alumnos.editar');
        });
    });
});
