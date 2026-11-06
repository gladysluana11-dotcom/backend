<?php

use App\Http\Controllers\Api\V1\AlumnoController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ColegioController;
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

    Route::middleware(['auth:sanctum', 'equipo.permisos'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/alumnos/{id}', [AlumnoController::class, 'show'])->middleware('permission:alumnos.ver');
        Route::patch('/alumnos/{id}', [AlumnoController::class, 'update'])->middleware('permission:alumnos.editar');
    });
});
