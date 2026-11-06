<?php

use App\Models\AnioEscolar;
use App\Models\Colegio;
use App\Models\Periodo;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(RefreshDatabase::class);

function crearColegioAcademico(string $subdominio): Colegio
{
    return Colegio::query()->create([
        'nombre' => 'Colegio '.$subdominio,
        'subdominio' => $subdominio,
        'colores' => ['primario' => '#123456'],
        'activo' => true,
    ]);
}

/**
 * @param  list<string>  $permisos
 */
function crearUsuarioAcademico(Colegio $colegio, string $rol, array $permisos, string $email): Usuario
{
    $usuario = Usuario::query()->create([
        'nombre' => 'Usuario',
        'apellido' => $rol,
        'email' => $email,
        'password' => Hash::make('Password123!'),
        'activo' => true,
    ]);
    $usuario->forceFill(['colegio_id' => $colegio->id])->save();

    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    setPermissionsTeamId($colegio->id);
    $rolModelo = Role::findOrCreate($rol, 'web');
    $rolModelo->syncPermissions($permisos);
    $usuario->assignRole($rolModelo);
    setPermissionsTeamId(null);

    return $usuario;
}

function crearAnioEscolarPorApi(TestCase $prueba, string $nombre = '2026'): int
{
    $respuesta = $prueba->postJson('/api/v1/anios-escolares', [
        'nombre' => $nombre,
        'fecha_inicio' => $nombre.'-03-01',
        'fecha_fin' => $nombre.'-12-20',
    ])->assertCreated();

    return $respuesta->json('data.anio_escolar.id');
}

test('mantiene un solo año activo y bloquea la reapertura de años cerrados', function () {
    $colegio = crearColegioAcademico('anio-unico');
    $director = crearUsuarioAcademico(
        $colegio,
        'Director',
        ['academico.ver', 'academico.crear', 'academico.editar'],
        'director-anio@example.test',
    );
    $this->actingAs($director);

    $anio2026 = crearAnioEscolarPorApi($this);
    $this->postJson("/api/v1/anios-escolares/{$anio2026}/abrir")->assertOk();
    $anio2027 = crearAnioEscolarPorApi($this, '2027');

    $this->postJson("/api/v1/anios-escolares/{$anio2027}/abrir")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('anio_escolar');

    $nivel = $this->postJson('/api/v1/niveles', ['nombre' => 'Primaria', 'orden' => 1])
        ->assertCreated()->json('data.nivel.id');
    $grado = $this->postJson('/api/v1/grados', [
        'nivel_id' => $nivel,
        'nombre' => '1ro',
        'orden' => 1,
    ])->assertCreated()->json('data.grado.id');

    $this->postJson("/api/v1/anios-escolares/{$anio2026}/cerrar")->assertOk();
    $this->putJson("/api/v1/anios-escolares/{$anio2026}", ['nombre' => '2026'])
        ->assertUnprocessable();
    $this->postJson('/api/v1/secciones', [
        'anio_escolar_id' => $anio2026,
        'grado_id' => $grado,
        'nombre' => 'A',
        'capacidad' => 30,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('anio_escolar_id');
    $this->postJson("/api/v1/anios-escolares/{$anio2026}/abrir")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('anio_escolar');

    $this->assertDatabaseHas('anios_escolares', [
        'id' => $anio2026,
        'colegio_id' => $colegio->id,
        'activo' => false,
        'cerrado' => true,
    ]);
});

test('aisla la estructura por colegio y desactiva niveles sin borrarlos', function () {
    $colegioA = crearColegioAcademico('estructura-a');
    $colegioB = crearColegioAcademico('estructura-b');
    $permisos = ['academico.ver', 'academico.crear', 'academico.editar', 'academico.desactivar'];
    $directorA = crearUsuarioAcademico($colegioA, 'Director', $permisos, 'director-estructura-a@example.test');
    $directorB = crearUsuarioAcademico($colegioB, 'Director', $permisos, 'director-estructura-b@example.test');

    $nivelA = $this->actingAs($directorA)->postJson('/api/v1/niveles', [
        'nombre' => 'Primaria',
        'orden' => 1,
    ])->assertCreated()->json('data.nivel.id');
    $anioA = crearAnioEscolarPorApi($this);
    $gradoA = $this->postJson('/api/v1/grados', [
        'nivel_id' => $nivelA,
        'nombre' => '1ro',
        'orden' => 1,
    ])->assertCreated()->json('data.grado.id');
    $seccionA = $this->postJson('/api/v1/secciones', [
        'anio_escolar_id' => $anioA,
        'grado_id' => $gradoA,
        'nombre' => 'A',
        'capacidad' => 30,
    ])->assertCreated()->json('data.seccion.id');
    $cursoA = $this->postJson('/api/v1/cursos', [
        'nivel_id' => $nivelA,
        'grado_id' => $gradoA,
        'nombre' => 'Comunicación',
    ])->assertCreated()->json('data.curso.id');

    $nivelB = $this->actingAs($directorB)->postJson('/api/v1/niveles', [
        'nombre' => 'Secundaria',
        'orden' => 2,
    ])->assertCreated()->json('data.nivel.id');

    $this->actingAs($directorA)->getJson('/api/v1/niveles?activo=true&per_page=1&sort=nombre&direction=desc')
        ->assertOk()
        ->assertJsonCount(1, 'data.niveles')
        ->assertJsonPath('data.paginacion.por_pagina', 1);
    $this->getJson('/api/v1/niveles?per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
    $this->getJson('/api/v1/niveles?sort=colegio_id')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort');
    $this->getJson("/api/v1/niveles/{$nivelB}")->assertNotFound();
    $this->putJson("/api/v1/niveles/{$nivelB}", ['nombre' => 'Modificado'])
        ->assertNotFound();

    $this->deleteJson("/api/v1/niveles/{$nivelA}")
        ->assertOk()
        ->assertJsonPath('data.nivel.activo', false);
    $this->deleteJson("/api/v1/grados/{$gradoA}")
        ->assertOk()
        ->assertJsonPath('data.grado.activo', false);
    $this->deleteJson("/api/v1/secciones/{$seccionA}")
        ->assertOk()
        ->assertJsonPath('data.seccion.activo', false);
    $this->deleteJson("/api/v1/cursos/{$cursoA}")
        ->assertOk()
        ->assertJsonPath('data.curso.activo', false);
    $this->assertDatabaseHas('niveles', ['id' => $nivelA, 'colegio_id' => $colegioA->id]);
    $this->assertDatabaseHas('grados', ['id' => $gradoA, 'colegio_id' => $colegioA->id]);
    $this->assertDatabaseHas('secciones', ['id' => $seccionA, 'colegio_id' => $colegioA->id]);
    $this->assertDatabaseHas('cursos', ['id' => $cursoA, 'colegio_id' => $colegioA->id]);

    $docente = crearUsuarioAcademico(
        $colegioA,
        'Docente',
        ['academico.ver'],
        'docente-estructura@example.test',
    );
    $this->actingAs($docente)->postJson('/api/v1/niveles', [
        'nombre' => 'Inicial',
        'orden' => 3,
    ])->assertForbidden();
});

test('valida fechas de periodos y evita unidades didacticas solapadas', function () {
    $colegio = crearColegioAcademico('fechas-academicas');
    $director = crearUsuarioAcademico(
        $colegio,
        'Director',
        ['academico.ver', 'academico.crear', 'academico.editar'],
        'director-fechas@example.test',
    );
    $this->actingAs($director);
    $anio = crearAnioEscolarPorApi($this);

    $this->postJson('/api/v1/periodos', [
        'anio_escolar_id' => $anio,
        'nombre' => 'Primer periodo',
        'orden' => 1,
        'fecha_inicio' => '2025-03-01',
        'fecha_fin' => '2025-06-30',
    ])->assertUnprocessable();

    $periodo = $this->postJson('/api/v1/periodos', [
        'anio_escolar_id' => $anio,
        'nombre' => 'Primer periodo',
        'orden' => 1,
        'fecha_inicio' => '2026-03-01',
        'fecha_fin' => '2026-06-30',
    ])->assertCreated()->json('data.periodo.id');

    $this->postJson('/api/v1/unidades-didacticas', [
        'periodo_id' => $periodo,
        'nombre' => 'Unidad 1',
        'orden' => 1,
        'fecha_inicio' => '2026-03-01',
        'fecha_fin' => '2026-04-15',
    ])->assertCreated();
    $this->postJson('/api/v1/unidades-didacticas', [
        'periodo_id' => $periodo,
        'nombre' => 'Unidad 2',
        'orden' => 2,
        'fecha_inicio' => '2026-04-15',
        'fecha_fin' => '2026-05-30',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('fecha_inicio');
});

test('valida docentes y asignaciones exclusivas de curso por sección y año', function () {
    $colegioA = crearColegioAcademico('asignacion-a');
    $colegioB = crearColegioAcademico('asignacion-b');
    $director = crearUsuarioAcademico(
        $colegioA,
        'Director',
        ['academico.ver', 'academico.crear', 'academico.editar'],
        'director-asignacion@example.test',
    );
    $docente = crearUsuarioAcademico(
        $colegioA,
        'Docente',
        ['academico.ver', 'academico.asignaciones.propias'],
        'docente-asignacion@example.test',
    );
    $docenteAjeno = crearUsuarioAcademico(
        $colegioB,
        'Docente',
        ['academico.ver', 'academico.asignaciones.propias'],
        'docente-ajeno@example.test',
    );
    $this->actingAs($director);

    $anio = crearAnioEscolarPorApi($this);
    $nivel = $this->postJson('/api/v1/niveles', ['nombre' => 'Primaria', 'orden' => 1])
        ->assertCreated()->json('data.nivel.id');
    $grado = $this->postJson('/api/v1/grados', [
        'nivel_id' => $nivel,
        'nombre' => '1ro',
        'orden' => 1,
    ])->assertCreated()->json('data.grado.id');
    $seccion = $this->postJson('/api/v1/secciones', [
        'anio_escolar_id' => $anio,
        'grado_id' => $grado,
        'nombre' => 'A',
        'capacidad' => 30,
    ])->assertCreated()->json('data.seccion.id');
    $curso = $this->postJson('/api/v1/cursos', [
        'nivel_id' => $nivel,
        'grado_id' => $grado,
        'nombre' => 'Comunicación',
    ])->assertCreated()->json('data.curso.id');

    $datos = [
        'anio_escolar_id' => $anio,
        'curso_id' => $curso,
        'seccion_id' => $seccion,
        'docente_id' => $docente->id,
    ];
    $this->postJson('/api/v1/asignaciones-docentes', $datos)->assertCreated();
    $this->postJson('/api/v1/asignaciones-docentes', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('curso_id');
    $this->postJson('/api/v1/asignaciones-docentes', [...$datos, 'docente_id' => $docenteAjeno->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('docente_id');

    $this->actingAs($docente)->getJson('/api/v1/asignaciones-docentes/mias?per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.asignaciones')
        ->assertJsonPath('data.paginacion.por_pagina', 1);
    $this->getJson('/api/v1/asignaciones-docentes')->assertForbidden();
});

test('configuración y funciones usan valores por defecto, permisos y auditoría aislados', function () {
    $colegioA = crearColegioAcademico('configuracion-a');
    $colegioB = crearColegioAcademico('configuracion-b');
    $permisos = ['configuracion.ver', 'configuracion.editar', 'funciones.ver', 'funciones.editar'];
    $directorA = crearUsuarioAcademico($colegioA, 'Director', $permisos, 'director-config-a@example.test');
    $directorB = crearUsuarioAcademico($colegioB, 'Director', $permisos, 'director-config-b@example.test');
    $docente = crearUsuarioAcademico(
        $colegioA,
        'Docente',
        ['academico.ver'],
        'docente-config@example.test',
    );

    $this->actingAs($directorA)->getJson('/api/v1/configuracion')
        ->assertOk()
        ->assertJsonPath('data.configuracion.numero_cuotas_pension', 10)
        ->assertJsonPath('data.configuracion.frecuencia_registro_avance', 'semanal');
    $this->putJson('/api/v1/configuracion', [
        'numero_cuotas_pension' => 9,
        'dia_vencimiento' => 15,
        'serie_recibos' => 'B001',
        'frecuencia_registro_avance' => 'quincenal',
        'recargo_mora_activo' => true,
    ])->assertOk();
    $this->putJson('/api/v1/configuracion', [
        'colegio_id' => $colegioB->id,
        'numero_cuotas_pension' => 12,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('colegio_id');
    $this->getJson('/api/v1/funciones')
        ->assertOk()
        ->assertJsonPath('data.funciones.portal_apoderados', false)
        ->assertJsonPath('data.funciones.recargo_mora', false);
    $this->putJson('/api/v1/funciones', [
        'portal_apoderados' => true,
        'notificaciones_push' => false,
        'recargo_mora' => true,
    ])->assertOk();

    $this->actingAs($directorB)->getJson('/api/v1/configuracion')
        ->assertOk()
        ->assertJsonPath('data.configuracion.numero_cuotas_pension', 10);
    $this->actingAs($docente)->getJson('/api/v1/configuracion')->assertForbidden();
    $this->putJson('/api/v1/funciones', ['portal_apoderados' => true])->assertForbidden();

    $this->assertDatabaseHas('auditorias', [
        'colegio_id' => $colegioA->id,
        'evento' => 'configuraciones_colegio.actualizado',
        'entidad' => 'configuraciones_colegio',
    ]);
    $this->assertDatabaseHas('auditorias', [
        'colegio_id' => $colegioA->id,
        'evento' => 'funciones_colegio.actualizado',
        'entidad' => 'funciones_colegio',
    ]);
});

test('la importación reporta errores y confirma el archivo validado una sola vez', function () {
    Storage::fake('local');
    $colegio = crearColegioAcademico('importacion-academica');
    $director = crearUsuarioAcademico(
        $colegio,
        'Director',
        ['academico.ver', 'academico.crear', 'academico.importar'],
        'director-importacion@example.test',
    );
    $this->actingAs($director);
    $anio = crearAnioEscolarPorApi($this);

    $this->post('/api/v1/importaciones/estructura-academica/validar', [
        'anio_escolar_id' => $anio,
        'archivo' => UploadedFile::fake()->createWithContent(
            'errores.csv',
            "Nivel,Grado,Sección,Capacidad,Curso\nPrimaria,1ro,A,0,Comunicación\n",
        ),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('data.errores.0.fila', 2);
    $this->assertDatabaseCount('niveles', 0);

    $filas = "Nivel,Grado,Sección,Capacidad,Curso\n".str_repeat("Primaria,1ro,A,30,Comunicación\n", 1001);
    $this->post('/api/v1/importaciones/estructura-academica/validar', [
        'anio_escolar_id' => $anio,
        'archivo' => UploadedFile::fake()->createWithContent('muchas-filas.csv', $filas),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('data.errores.0.fila', 1002);

    $this->post('/api/v1/importaciones/estructura-academica/validar', [
        'anio_escolar_id' => $anio,
        'archivo' => UploadedFile::fake()->createWithContent(
            'archivo-grande.csv',
            str_repeat('x', 2 * 1024 * 1024 + 1),
        ),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('archivo');

    $validacion = $this->post('/api/v1/importaciones/estructura-academica/validar', [
        'anio_escolar_id' => $anio,
        'archivo' => UploadedFile::fake()->createWithContent(
            'estructura.csv',
            "Nivel,Grado,Sección,Capacidad,Curso\nPrimaria,1ro,A,30,Comunicación\nPrimaria,1ro,A,30,Matemática\n",
        ),
    ], ['Accept' => 'application/json'])->assertOk();
    $token = $validacion->json('data.token');

    expect($token)->toBeString()->toHaveLength(64);
    $this->assertDatabaseCount('niveles', 0);
    $this->postJson('/api/v1/importaciones/estructura-academica/confirmar', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.niveles', 1)
        ->assertJsonPath('data.grados', 1)
        ->assertJsonPath('data.secciones', 1)
        ->assertJsonPath('data.cursos', 2);
    $this->postJson('/api/v1/importaciones/estructura-academica/confirmar', ['token' => $token])
        ->assertUnprocessable();
});

test('el seeder carga Salesian Innova con periodos y unidades sin solapamientos', function () {
    $this->seed(DatabaseSeeder::class);

    $colegio = Colegio::query()->where('subdominio', 'salesian-innova')->firstOrFail();
    $anio = AnioEscolar::query()
        ->where('colegio_id', $colegio->id)
        ->where('nombre', '2026')
        ->firstOrFail();
    $periodos = Periodo::query()
        ->where('colegio_id', $colegio->id)
        ->where('anio_escolar_id', $anio->id)
        ->orderBy('orden')
        ->get();

    expect($periodos)->toHaveCount(4);
    $this->assertDatabaseCount('unidades_didacticas', 8);
    $this->assertDatabaseCount('asignaciones_docentes', 1);
    $this->assertDatabaseHas('niveles', ['colegio_id' => $colegio->id, 'nombre' => 'Primaria']);
    $this->assertDatabaseHas('niveles', ['colegio_id' => $colegio->id, 'nombre' => 'Secundaria']);

    foreach ($periodos as $periodo) {
        $unidades = $periodo->unidades()->orderBy('orden')->get();

        expect($unidades)->toHaveCount(2)
            ->and(Carbon::parse($unidades[0]->fecha_fin)->lessThan(
                Carbon::parse($unidades[1]->fecha_inicio),
            ))->toBeTrue();
    }
});
