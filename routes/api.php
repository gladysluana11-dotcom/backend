<?php

use App\Http\Controllers\Api\V1\AlumnoController;
use App\Http\Controllers\Api\V1\AnioEscolarController;
use App\Http\Controllers\Api\V1\AsignacionDocenteController;
use App\Http\Controllers\Api\V1\AuditoriaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ColegioController;
use App\Http\Controllers\Api\V1\ConfiguracionColegioController;
use App\Http\Controllers\Api\V1\CursoController;
use App\Http\Controllers\Api\V1\FuncionColegioController;
use App\Http\Controllers\Api\V1\GradoController;
use App\Http\Controllers\Api\V1\ImportacionAcademicaController;
use App\Http\Controllers\Api\V1\NivelController;
use App\Http\Controllers\Api\V1\PeriodoController;
use App\Http\Controllers\Api\V1\PermisoController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\SeccionController;
use App\Http\Controllers\Api\V1\UnidadDidacticaController;
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

            Route::apiResource('anios-escolares', AnioEscolarController::class)
                ->only(['index', 'show', 'store', 'update'])
                ->parameters(['anios-escolares' => 'anio_escolar']);
            Route::post('/anios-escolares/{anio_escolar}/abrir', [AnioEscolarController::class, 'abrir']);
            Route::post('/anios-escolares/{anio_escolar}/cerrar', [AnioEscolarController::class, 'cerrar']);

            Route::apiResource('niveles', NivelController::class)
                ->parameters(['niveles' => 'nivel']);
            Route::apiResource('grados', GradoController::class)
                ->parameters(['grados' => 'grado']);
            Route::apiResource('secciones', SeccionController::class)
                ->parameters(['secciones' => 'seccion']);
            Route::apiResource('cursos', CursoController::class)
                ->parameters(['cursos' => 'curso']);
            Route::apiResource('periodos', PeriodoController::class)
                ->parameters(['periodos' => 'periodo']);
            Route::apiResource('unidades-didacticas', UnidadDidacticaController::class)
                ->parameters(['unidades-didacticas' => 'unidad_didactica']);

            Route::get('/asignaciones-docentes/mias', [AsignacionDocenteController::class, 'misAsignaciones']);
            Route::apiResource('asignaciones-docentes', AsignacionDocenteController::class)
                ->parameters(['asignaciones-docentes' => 'asignacion']);

            Route::get('/configuracion', [ConfiguracionColegioController::class, 'show']);
            Route::put('/configuracion', [ConfiguracionColegioController::class, 'update']);
            Route::get('/funciones', [FuncionColegioController::class, 'show']);
            Route::put('/funciones', [FuncionColegioController::class, 'update']);

            Route::get('/importaciones/estructura-academica/plantilla', [ImportacionAcademicaController::class, 'plantilla']);
            Route::post('/importaciones/estructura-academica/validar', [ImportacionAcademicaController::class, 'validar']);
            Route::post('/importaciones/estructura-academica/confirmar', [ImportacionAcademicaController::class, 'confirmar']);
        });
    });
});
