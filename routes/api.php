<?php

use App\Http\Controllers\Api\V1\AlumnoController;
use App\Http\Controllers\Api\V1\AnioEscolarController;
use App\Http\Controllers\Api\V1\ApoderadoController;
use App\Http\Controllers\Api\V1\AsignacionDocenteController;
use App\Http\Controllers\Api\V1\AsignacionSeccionController;
use App\Http\Controllers\Api\V1\AuditoriaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ColegioController;
use App\Http\Controllers\Api\V1\ConfiguracionColegioController;
use App\Http\Controllers\Api\V1\CursoController;
use App\Http\Controllers\Api\V1\DocumentoRequeridoController;
use App\Http\Controllers\Api\V1\FuncionColegioController;
use App\Http\Controllers\Api\V1\GradoController;
use App\Http\Controllers\Api\V1\ImportacionAcademicaController;
use App\Http\Controllers\Api\V1\ImportacionAlumnoController;
use App\Http\Controllers\Api\V1\MatriculaController;
use App\Http\Controllers\Api\V1\MatriculaDocumentoController;
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

            Route::get('/alumnos', [AlumnoController::class, 'index'])->middleware('permission:alumnos.ver');
            Route::post('/alumnos', [AlumnoController::class, 'store'])->middleware('permission:alumnos.crear');
            Route::get('/alumnos/{alumno}/historial', [AlumnoController::class, 'historial'])
                ->middleware('permission:alumnos.ver');
            Route::post('/alumnos/{alumno}/foto', [AlumnoController::class, 'cargarFoto'])
                ->middleware('permission:alumnos.editar');
            Route::get('/alumnos/{alumno}/foto', [AlumnoController::class, 'foto'])
                ->middleware('permission:alumnos.ver')
                ->name('api.v1.alumnos.foto');
            Route::get('/alumnos/{id}', [AlumnoController::class, 'show'])->middleware('permission:alumnos.ver');
            Route::patch('/alumnos/{id}', [AlumnoController::class, 'update'])->middleware('permission:alumnos.editar');

            Route::get('/apoderados', [ApoderadoController::class, 'index'])->middleware('permission:apoderados.ver');
            Route::post('/alumnos/{alumno}/apoderados', [ApoderadoController::class, 'crearYVincular'])
                ->middleware('permission:apoderados.crear');
            Route::put('/alumnos/{alumno}/apoderados/{apoderado}', [ApoderadoController::class, 'vincular'])
                ->middleware('permission:apoderados.editar');
            Route::get('/apoderados/{apoderado}', [ApoderadoController::class, 'show'])
                ->middleware('permission:apoderados.ver');
            Route::patch('/apoderados/{apoderado}', [ApoderadoController::class, 'update'])
                ->middleware('permission:apoderados.editar');

            Route::get('/matriculas', [MatriculaController::class, 'index'])->middleware('permission:matriculas.ver');
            Route::post('/matriculas', [MatriculaController::class, 'store'])->middleware('permission:matriculas.gestionar');
            Route::get('/matriculas/{matricula}/ficha', [MatriculaController::class, 'ficha'])
                ->middleware('permission:matriculas.ver');
            Route::post('/matriculas/{matricula}/estado', [MatriculaController::class, 'cambiarEstado'])
                ->middleware('permission:matriculas.gestionar');
            Route::post('/matriculas/{matricula}/cambiar-seccion', [MatriculaController::class, 'cambiarSeccion'])
                ->middleware('permission:matriculas.gestionar');
            Route::get('/matriculas/{matricula}', [MatriculaController::class, 'show'])
                ->middleware('permission:matriculas.ver');

            Route::get('/documentos-requeridos', [DocumentoRequeridoController::class, 'index'])
                ->middleware('permission:matriculas.ver');
            Route::post('/documentos-requeridos', [DocumentoRequeridoController::class, 'store'])
                ->middleware('permission:matriculas.gestionar');
            Route::put('/documentos-requeridos/{documento}', [DocumentoRequeridoController::class, 'update'])
                ->middleware('permission:matriculas.gestionar');
            Route::patch('/matricula-documentos/{documento}', [MatriculaDocumentoController::class, 'update'])
                ->middleware('permission:matriculas.gestionar');
            Route::post('/asignaciones-seccion', [AsignacionSeccionController::class, 'store'])
                ->middleware('permission:matriculas.gestionar');
            Route::get('/asignaciones-seccion', [AsignacionSeccionController::class, 'index'])
                ->middleware('permission:matriculas.ver');

            Route::get('/importaciones/alumnos/plantilla', [ImportacionAlumnoController::class, 'plantilla'])
                ->middleware('permission:alumnos.importar');
            Route::post('/importaciones/alumnos/validar', [ImportacionAlumnoController::class, 'validar'])
                ->middleware('permission:alumnos.importar');
            Route::post('/importaciones/alumnos/confirmar', [ImportacionAlumnoController::class, 'confirmar'])
                ->middleware('permission:alumnos.importar');

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
