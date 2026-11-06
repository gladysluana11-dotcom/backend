<?php

use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\AsignacionSeccion;
use App\Models\Colegio;
use App\Models\Grado;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Seccion;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function permisosDirectorB4(): array
{
    return [
        'alumnos.ver',
        'alumnos.crear',
        'alumnos.editar',
        'alumnos.importar',
        'apoderados.ver',
        'apoderados.crear',
        'apoderados.editar',
        'matriculas.ver',
        'matriculas.gestionar',
        'matriculas.exceso_vacantes',
        'academico.ver',
        'academico.crear',
        'academico.editar',
    ];
}

function crearColegioB4(string $subdominio): Colegio
{
    return Colegio::query()->create([
        'nombre' => 'Colegio '.$subdominio,
        'subdominio' => $subdominio,
        'colores' => ['primario' => '#123456'],
        'activo' => true,
    ]);
}

/**
 * @param  list<string>|null  $permisos
 */
function crearUsuarioB4(Colegio $colegio, string $email, string $rol = 'Director', ?array $permisos = null): Usuario
{
    $permisos ??= $rol === 'Director'
        ? permisosDirectorB4()
        : ['alumnos.ver', 'matriculas.ver'];
    $usuario = Usuario::query()->create([
        'nombre' => 'Usuario',
        'apellido' => 'Ficticio',
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

/**
 * @return array{colegio: Colegio, usuario: Usuario, anio: AnioEscolar, grado: Grado, seccion: Seccion}
 */
function prepararAcademiaB4(string $subdominio, int $capacidad = 30): array
{
    $colegio = crearColegioB4($subdominio);
    $usuario = crearUsuarioB4($colegio, 'director@'.$subdominio.'.test');
    $anio = AnioEscolar::query()->create([
        'colegio_id' => $colegio->id,
        'nombre' => '2026',
        'fecha_inicio' => '2026-03-02',
        'fecha_fin' => '2026-12-18',
        'activo' => true,
        'cerrado' => false,
    ]);
    $nivel = Nivel::query()->create([
        'colegio_id' => $colegio->id,
        'nombre' => 'Primaria',
        'orden' => 1,
        'activo' => true,
    ]);
    $grado = Grado::query()->create([
        'colegio_id' => $colegio->id,
        'nivel_id' => $nivel->id,
        'nombre' => '1ro',
        'orden' => 1,
        'activo' => true,
    ]);
    $seccion = Seccion::query()->create([
        'colegio_id' => $colegio->id,
        'anio_escolar_id' => $anio->id,
        'grado_id' => $grado->id,
        'nombre' => 'A',
        'capacidad' => $capacidad,
        'activo' => true,
    ]);

    return compact('colegio', 'usuario', 'anio', 'grado', 'seccion');
}

function crearAlumnoApiB4(TestCase $prueba, string $documento): int
{
    return $prueba->postJson('/api/v1/alumnos', [
        'nombre' => 'Estudiante Ficticio',
        'apellido' => 'Prueba',
        'tipo_documento' => 'DNI',
        'documento_identidad' => $documento,
        'fecha_nacimiento' => '2015-03-15',
        'sexo' => 'F',
        'direccion' => 'Dirección ficticia',
    ])->assertCreated()->json('data.alumno.id');
}

function vincularResponsableApiB4(TestCase $prueba, int $alumnoId): int
{
    return $prueba->postJson("/api/v1/alumnos/{$alumnoId}/apoderados", [
        'nombre' => 'Apoderado Ficticio',
        'apellido' => 'Familia de Prueba',
        'parentesco' => 'Madre',
        'telefono' => '900000001',
        'email' => 'apoderado@ejemplo.test',
        'responsable_pago' => true,
    ])->assertCreated()->json('data.apoderado.id');
}

function archivoSubidoRealB4(string $nombre, string $contenido): UploadedFile
{
    $ruta = tempnam(sys_get_temp_dir(), 'sge_b4_upload_');

    if ($ruta === false || file_put_contents($ruta, $contenido) === false) {
        throw new RuntimeException('No se pudo crear el archivo temporal de prueba.');
    }

    return new UploadedFile($ruta, $nombre, null, UPLOAD_ERR_OK, true);
}

/**
 * @param  array<string, mixed>  $sobrescrituras
 */
function matricularApiB4(TestCase $prueba, array $academia, int $alumnoId, array $sobrescrituras = []): int
{
    return $prueba->postJson('/api/v1/matriculas', [
        'alumno_id' => $alumnoId,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'fecha_matricula' => '2026-03-02',
        ...$sobrescrituras,
    ])->assertCreated()->json('data.matricula.id');
}

test('crea y lista alumnos sin exponer documento completo ni dirección', function () {
    $academia = prepararAcademiaB4('alumnos-lista');
    $this->actingAs($academia['usuario'], 'sanctum');
    $alumnoId = crearAlumnoApiB4($this, '123456789');

    $this->getJson('/api/v1/alumnos?buscar=123456789')
        ->assertOk()
        ->assertJsonPath('data.alumnos.0.id', $alumnoId)
        ->assertJsonPath('data.alumnos.0.documento', '*****6789')
        ->assertJsonMissingPath('data.alumnos.0.direccion')
        ->assertJsonMissingPath('data.alumnos.0.documento_identidad');

    $this->assertDatabaseHas('alumnos', [
        'id' => $alumnoId,
        'colegio_id' => $academia['colegio']->id,
        'documento_identidad' => '123456789',
    ]);
});

test('rechaza documentos duplicados incluso si el alumno anterior fue retirado', function () {
    $academia = prepararAcademiaB4('alumnos-documento');
    $this->actingAs($academia['usuario'], 'sanctum');
    $alumnoId = crearAlumnoApiB4($this, '12345678');
    Alumno::query()->whereKey($alumnoId)->update(['estado' => 'retirado', 'activo' => false]);

    $this->postJson('/api/v1/alumnos', [
        'nombre' => 'Otro Alumno',
        'apellido' => 'Ficticio',
        'tipo_documento' => 'DNI',
        'documento_identidad' => '12345678',
        'fecha_nacimiento' => '2015-03-15',
        'sexo' => 'M',
        'direccion' => 'Dirección ficticia',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('documento_identidad');

    $this->assertDatabaseCount('alumnos', 1);
});

test('exige responsable de pago y permite reutilizar un apoderado entre hermanos', function () {
    $academia = prepararAcademiaB4('apoderados-hermanos');
    $this->actingAs($academia['usuario'], 'sanctum');
    $alumnoA = crearAlumnoApiB4($this, '12345671');
    $alumnoB = crearAlumnoApiB4($this, '12345672');

    $this->postJson('/api/v1/matriculas', [
        'alumno_id' => $alumnoA,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'fecha_matricula' => '2026-03-02',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('alumno_id');

    $apoderadoId = vincularResponsableApiB4($this, $alumnoA);
    $this->putJson("/api/v1/alumnos/{$alumnoB}/apoderados/{$apoderadoId}", [
        'parentesco' => 'Madre',
        'telefono' => '900000002',
        'email' => 'familia@ejemplo.test',
        'responsable_pago' => true,
    ])->assertOk();

    $this->assertDatabaseCount('apoderados', 1);
    $this->assertDatabaseCount('alumno_apoderado', 2);

    $this->postJson('/api/v1/matriculas', [
        'alumno_id' => $alumnoA,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'fecha_matricula' => '2026-03-02',
    ])->assertCreated();
});

test('limita matrículas vigentes en el servicio y con el índice generado MySQL', function () {
    $academia = prepararAcademiaB4('matricula-unica');
    $this->actingAs($academia['usuario'], 'sanctum');
    $alumnoId = crearAlumnoApiB4($this, '12345673');
    vincularResponsableApiB4($this, $alumnoId);
    $matriculaId = matricularApiB4($this, $academia, $alumnoId);

    $this->postJson('/api/v1/matriculas', [
        'alumno_id' => $alumnoId,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'fecha_matricula' => '2026-03-02',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('alumno_id');

    $matricula = Matricula::query()->findOrFail($matriculaId);
    expect(fn () => DB::table('matriculas')->insert([
        'colegio_id' => $academia['colegio']->id,
        'alumno_id' => $alumnoId,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'usuario_id' => $academia['usuario']->id,
        'anio_escolar' => '2026',
        'grado' => '1ro',
        'seccion' => 'A',
        'estado' => 'pendiente_documentos',
        'fecha_matricula' => '2026-03-02',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
    $this->assertModelExists($matricula);
});

test('impide exceder vacantes salvo autorización del Director con motivo auditado', function () {
    $academia = prepararAcademiaB4('vacantes', 1);
    $this->actingAs($academia['usuario'], 'sanctum');
    $alumnoA = crearAlumnoApiB4($this, '12345674');
    $alumnoB = crearAlumnoApiB4($this, '12345675');
    vincularResponsableApiB4($this, $alumnoA);
    vincularResponsableApiB4($this, $alumnoB);
    matricularApiB4($this, $academia, $alumnoA);

    $this->postJson('/api/v1/matriculas', [
        'alumno_id' => $alumnoB,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'fecha_matricula' => '2026-03-02',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('seccion_id');

    $administrador = crearUsuarioB4($academia['colegio'], 'admin@vacantes.test', 'Administrador', [
        'matriculas.ver',
        'matriculas.gestionar',
        'matriculas.exceso_vacantes',
    ]);
    $this->actingAs($administrador, 'sanctum')->postJson('/api/v1/matriculas', [
        'alumno_id' => $alumnoB,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'fecha_matricula' => '2026-03-02',
        'autorizar_exceso' => true,
        'motivo_exceso' => 'No es una autorización del Director.',
    ])->assertForbidden();

    $this->actingAs($academia['usuario'], 'sanctum');
    $this->postJson('/api/v1/matriculas', [
        'alumno_id' => $alumnoB,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'fecha_matricula' => '2026-03-02',
        'autorizar_exceso' => true,
        'motivo_exceso' => 'Continuidad escolar autorizada.',
    ])->assertCreated();

    $this->assertDatabaseHas('auditorias', [
        'colegio_id' => $academia['colegio']->id,
        'evento' => 'matriculas.exceso_vacantes_autorizado',
    ]);
});

test('conserva el historial al cambiar de sección y retirar al alumno', function () {
    $academia = prepararAcademiaB4('matricula-historial');
    $seccionB = Seccion::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'nombre' => 'B',
        'capacidad' => 10,
        'activo' => true,
    ]);
    $this->actingAs($academia['usuario'], 'sanctum');
    $alumnoId = crearAlumnoApiB4($this, '12345676');
    vincularResponsableApiB4($this, $alumnoId);
    $matriculaId = matricularApiB4($this, $academia, $alumnoId);

    $this->postJson("/api/v1/matriculas/{$matriculaId}/cambiar-seccion", [
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $seccionB->id,
        'motivo' => 'Cambio de sección solicitado.',
        'fecha' => '2026-03-15',
    ])->assertOk();
    $this->postJson("/api/v1/matriculas/{$matriculaId}/estado", [
        'estado' => 'retirado',
        'motivo' => 'Traslado solicitado.',
        'fecha_retiro' => '2026-04-15',
    ])->assertOk()
        ->assertJsonPath('data.matricula.estado', 'retirado');

    $this->assertDatabaseHas('historial_matriculas', [
        'matricula_id' => $matriculaId,
        'evento' => 'seccion_cambiada',
        'seccion_nueva_id' => $seccionB->id,
    ]);
    $this->assertDatabaseHas('historial_matriculas', [
        'matricula_id' => $matriculaId,
        'evento' => 'retirada',
        'estado_nuevo' => 'retirado',
    ]);
});

test('mantiene matrículas pendientes hasta entregar los documentos obligatorios y genera ficha PDF', function () {
    Storage::fake('local');
    $academia = prepararAcademiaB4('documentos-pdf');
    Storage::disk('local')->put(
        'logos/colegio.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="#123456"/></svg>',
    );
    $academia['colegio']->forceFill(['logo' => 'logos/colegio.svg'])->save();
    $this->actingAs($academia['usuario'], 'sanctum');
    $this->postJson('/api/v1/documentos-requeridos', [
        'nombre' => 'Partida de nacimiento',
        'obligatorio' => true,
    ])->assertCreated();
    $alumnoId = crearAlumnoApiB4($this, '12345677');
    vincularResponsableApiB4($this, $alumnoId);
    $matriculaId = matricularApiB4($this, $academia, $alumnoId, ['estado' => 'matriculado']);

    $this->assertDatabaseHas('matriculas', ['id' => $matriculaId, 'estado' => 'pendiente_documentos']);
    $matricula = Matricula::query()->with('documentos')->findOrFail($matriculaId);
    $documentoId = $matricula->documentos->firstOrFail()->id;

    $this->patchJson("/api/v1/matricula-documentos/{$documentoId}", [
        'estado' => 'entregado',
        'fecha_entrega' => '2026-03-10',
    ])->assertOk();
    $this->postJson("/api/v1/matriculas/{$matriculaId}/estado", ['estado' => 'matriculado'])
        ->assertOk()
        ->assertJsonPath('data.matricula.estado', 'matriculado');

    $pdf = $this->getJson("/api/v1/matriculas/{$matriculaId}/ficha")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    expect(substr((string) $pdf->getContent(), 0, 4))->toBe('%PDF');
});

test('valida importaciones sin guardar y confirma el archivo validado una sola vez', function () {
    $academia = prepararAcademiaB4('importacion-alumnos');
    $this->actingAs($academia['usuario'], 'sanctum');
    $archivoInvalido = UploadedFile::fake()->createWithContent(
        'alumnos.csv',
        "nombres,apellidos,tipo_documento,documento_identidad,fecha_nacimiento,sexo,direccion\n,Prueba,DNI,12345678,2015-03-15,F,Direccion\n",
    );

    $this->postJson('/api/v1/importaciones/alumnos/validar', ['archivo' => $archivoInvalido])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.fila', 2)
        ->assertJsonPath('errors.0.campo', 'nombre');
    $this->assertDatabaseCount('alumnos', 0);

    $archivoValido = UploadedFile::fake()->createWithContent(
        'alumnos.csv',
        "nombres,apellidos,tipo_documento,documento_identidad,fecha_nacimiento,sexo,direccion\nAlumno Ficticio,Importado,DNI,12345679,2015-03-15,F,Direccion ficticia\n",
    );
    $validacion = $this->postJson('/api/v1/importaciones/alumnos/validar', ['archivo' => $archivoValido])
        ->assertOk()
        ->assertJsonPath('data.filas_validas', 1);
    $token = $validacion->json('data.token');
    $this->assertDatabaseCount('alumnos', 0);

    $this->postJson('/api/v1/importaciones/alumnos/confirmar', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.alumnos_importados', 1);
    $this->assertDatabaseHas('alumnos', ['documento_identidad' => '12345679']);
    $this->postJson('/api/v1/importaciones/alumnos/confirmar', ['token' => $token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

test('restringe la lectura docente a las secciones asignadas', function () {
    $academia = prepararAcademiaB4('docente-secciones');
    $seccionB = Seccion::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'nombre' => 'B',
        'capacidad' => 10,
        'activo' => true,
    ]);
    $this->actingAs($academia['usuario'], 'sanctum');
    $alumnoA = crearAlumnoApiB4($this, '12345680');
    $alumnoB = crearAlumnoApiB4($this, '12345681');
    Matricula::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'alumno_id' => $alumnoA,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'usuario_id' => $academia['usuario']->id,
        'anio_escolar' => '2026',
        'grado' => '1ro',
        'seccion' => 'A',
        'estado' => 'matriculado',
        'fecha_matricula' => '2026-03-02',
    ]);
    Matricula::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'alumno_id' => $alumnoB,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $seccionB->id,
        'usuario_id' => $academia['usuario']->id,
        'anio_escolar' => '2026',
        'grado' => '1ro',
        'seccion' => 'B',
        'estado' => 'matriculado',
        'fecha_matricula' => '2026-03-02',
    ]);
    $docente = crearUsuarioB4($academia['colegio'], 'docente@docente-secciones.test', 'Docente');
    AsignacionSeccion::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'anio_escolar_id' => $academia['anio']->id,
        'seccion_id' => $academia['seccion']->id,
        'usuario_id' => $docente->id,
        'activo' => true,
    ]);

    $this->actingAs($docente, 'sanctum')
        ->getJson('/api/v1/alumnos')
        ->assertOk()
        ->assertJsonCount(1, 'data.alumnos')
        ->assertJsonPath('data.alumnos.0.id', $alumnoA);
    $this->getJson("/api/v1/alumnos/{$alumnoB}")->assertForbidden();
});

test('sirve fotos privadas solo al colegio propietario y rechaza contenido que no sea imagen real', function () {
    Storage::fake('local');
    $academiaA = prepararAcademiaB4('foto-colegio-a');
    $academiaB = prepararAcademiaB4('foto-colegio-b');
    $this->actingAs($academiaB['usuario'], 'sanctum');
    $alumnoB = crearAlumnoApiB4($this, '12345682');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/YQAAAABJRU5ErkJggg==');
    $fotoValida = archivoSubidoRealB4('alumno.png', $png);

    $this->postJson("/api/v1/alumnos/{$alumnoB}/foto", [
        'foto' => $fotoValida,
    ])->assertOk();
    unlink($fotoValida->getPathname());
    $alumno = Alumno::query()->findOrFail($alumnoB);
    expect($alumno->foto_path)->not->toBeNull()
        ->and($alumno->foto_path)->not->toContain('public');
    Storage::disk('local')->assertExists($alumno->foto_path);
    $this->getJson("/api/v1/alumnos/{$alumnoB}/foto")->assertOk()
        ->assertHeader('content-type', 'image/png');

    $this->actingAs($academiaA['usuario'], 'sanctum')
        ->getJson("/api/v1/alumnos/{$alumnoB}/foto")
        ->assertNotFound();
    $fotoNoAutorizada = archivoSubidoRealB4('foto.png', $png);
    $this->postJson("/api/v1/alumnos/{$alumnoB}/foto", ['foto' => $fotoNoAutorizada])->assertNotFound();
    unlink($fotoNoAutorizada->getPathname());

    $fotoInvalida = archivoSubidoRealB4('imagen.jpg', 'esto no es una imagen');
    $this->actingAs($academiaB['usuario'], 'sanctum')
        ->postJson("/api/v1/alumnos/{$alumnoB}/foto", ['foto' => $fotoInvalida])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('foto');
    unlink($fotoInvalida->getPathname());
});

test('oculta alumnos, apoderados y matrículas de otros colegios', function () {
    $academiaA = prepararAcademiaB4('aislamiento-b4-a');
    $academiaB = prepararAcademiaB4('aislamiento-b4-b');
    $this->actingAs($academiaB['usuario'], 'sanctum');
    $alumnoB = crearAlumnoApiB4($this, '12345683');
    $apoderadoB = vincularResponsableApiB4($this, $alumnoB);
    $matriculaB = matricularApiB4($this, $academiaB, $alumnoB);

    $this->actingAs($academiaA['usuario'], 'sanctum');
    $this->getJson("/api/v1/alumnos/{$alumnoB}")->assertNotFound();
    $this->patchJson("/api/v1/alumnos/{$alumnoB}", ['nombre' => 'No autorizado'])->assertNotFound();
    $this->getJson("/api/v1/apoderados/{$apoderadoB}")->assertNotFound();
    $this->patchJson("/api/v1/apoderados/{$apoderadoB}", ['nombre' => 'No autorizado'])->assertNotFound();
    $this->putJson("/api/v1/alumnos/{$alumnoB}/apoderados/{$apoderadoB}", [
        'parentesco' => 'Otro',
        'responsable_pago' => false,
    ])->assertNotFound();
    $this->getJson("/api/v1/matriculas/{$matriculaB}")->assertNotFound();
    $this->postJson("/api/v1/matriculas/{$matriculaB}/estado", [
        'estado' => 'retirado',
        'motivo' => 'No autorizado.',
        'fecha_retiro' => '2026-04-15',
    ])->assertNotFound();

    $this->assertDatabaseHas('apoderados', ['id' => $apoderadoB, 'colegio_id' => $academiaB['colegio']->id]);
    $this->assertDatabaseHas('matriculas', ['id' => $matriculaB, 'colegio_id' => $academiaB['colegio']->id]);
});

test('siembra cuarenta alumnos ficticios con apoderados reutilizables y matrículas 2026', function () {
    $this->seed(DatabaseSeeder::class);
    $colegio = Colegio::query()->where('subdominio', 'salesian-innova')->firstOrFail();

    $this->assertDatabaseCount('alumnos', 40);
    $this->assertDatabaseCount('apoderados', 10);
    $this->assertDatabaseCount('alumno_apoderado', 40);
    $this->assertDatabaseCount('matriculas', 40);
    $seccionesConMatricula = DB::table('matriculas')
        ->where('colegio_id', $colegio->id)
        ->distinct()
        ->count('seccion_id');

    expect($seccionesConMatricula)->toBeGreaterThan(1);
});
