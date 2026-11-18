<?php

use App\Contracts\ConsultarAplicacionesPago;
use App\Events\MatriculaConfirmada;
use App\Events\MatriculaRetirada;
use App\Listeners\AnularCuotasAlRetirarMatricula;
use App\Listeners\GenerarCuotasAlConfirmarMatricula;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Apoderado;
use App\Models\Colegio;
use App\Models\ConfiguracionColegio;
use App\Models\Cuota;
use App\Models\Descuento;
use App\Models\Grado;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Seccion;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Policies\CuotaPolicy;
use App\Policies\DescuentoPolicy;
use App\Policies\TarifaPolicy;
use App\Services\GestionarMatriculas;
use App\Services\GestionarTarifas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function permisosPredeterminadosB5a(string $rol): array
{
    return match ($rol) {
        'Director' => [
            'alumnos.ver',
            'apoderados.ver',
            'matriculas.ver',
            'matriculas.gestionar',
            'matriculas.exceso_vacantes',
            'pagos.ver',
            'pagos.registrar',
            'tarifas.ver',
            'tarifas.gestionar',
            'descuentos.aprobar',
        ],
        'Administrador' => [
            'alumnos.ver',
            'apoderados.ver',
            'matriculas.ver',
            'matriculas.gestionar',
            'pagos.ver',
            'tarifas.ver',
            'tarifas.gestionar',
        ],
        'Tesorería' => [
            'alumnos.ver',
            'apoderados.ver',
            'matriculas.ver',
            'pagos.ver',
            'pagos.registrar',
            'tarifas.ver',
        ],
        default => ['alumnos.ver', 'matriculas.ver'],
    };
}

/**
 * @param  list<string>|null  $permisos
 */
function crearUsuarioB5a(
    Colegio $colegio,
    string $subdominio,
    string $rol = 'Director',
    ?array $permisos = null,
): Usuario {
    $permisos ??= permisosPredeterminadosB5a($rol);
    $correlativo = Usuario::query()->count() + 1;
    $usuario = Usuario::query()->create([
        'nombre' => $rol,
        'apellido' => 'Ficticio',
        'email' => strtolower(str_replace([' ', 'í'], ['', 'i'], $rol))
            .$correlativo.'@'.$subdominio.'.test',
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
 * @return array{
 *   colegio: Colegio,
 *   usuario: Usuario,
 *   anio: AnioEscolar,
 *   nivel: Nivel,
 *   grado: Grado,
 *   seccion: Seccion,
 *   configuracion: ConfiguracionColegio
 * }
 */
function prepararAcademiaB5a(
    string $subdominio,
    int $numeroCuotas = 3,
    int $diaVencimiento = 10,
): array {
    $colegio = Colegio::query()->create([
        'nombre' => 'Colegio '.$subdominio,
        'subdominio' => $subdominio,
        'colores' => ['primario' => '#123456'],
        'activo' => true,
    ]);
    $usuario = crearUsuarioB5a($colegio, $subdominio);
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
        'capacidad' => 30,
        'activo' => true,
    ]);
    $configuracion = ConfiguracionColegio::query()->create([
        'colegio_id' => $colegio->id,
        'numero_cuotas_pension' => $numeroCuotas,
        'dia_vencimiento' => $diaVencimiento,
        'serie_recibos' => 'F001',
        'frecuencia_registro_avance' => 'semanal',
        'recargo_mora_activo' => false,
    ]);

    return compact('colegio', 'usuario', 'anio', 'nivel', 'grado', 'seccion', 'configuracion');
}

function crearAlumnoB5a(array $academia, int $correlativo = 1): Alumno
{
    return Alumno::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'nombre' => 'Alumno',
        'apellido' => 'Ficticio '.$correlativo,
        'tipo_documento' => 'DNI',
        'documento_identidad' => 'B5A'.str_pad((string) $academia['colegio']->id, 5, '0', STR_PAD_LEFT)
            .str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT),
        'fecha_nacimiento' => '2015-03-15',
        'sexo' => 'F',
        'direccion' => 'Dirección ficticia',
        'estado' => 'activo',
        'activo' => true,
    ]);
}

function crearMatriculaB5a(array $academia, Alumno $alumno, array $sobrescrituras = []): Matricula
{
    return Matricula::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'alumno_id' => $alumno->id,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'usuario_id' => $academia['usuario']->id,
        'anio_escolar' => $academia['anio']->nombre,
        'grado' => $academia['grado']->nombre,
        'seccion' => $academia['seccion']->nombre,
        'estado' => 'matriculado',
        'fecha_matricula' => '2026-03-02',
        ...$sobrescrituras,
    ]);
}

function crearTarifaB5a(array $academia, array $sobrescrituras = []): Tarifa
{
    return Tarifa::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'monto_matricula' => '100.00',
        'monto_pension' => '100.00',
        'numero_cuotas' => $academia['configuracion']->numero_cuotas_pension,
        'dia_vencimiento' => $academia['configuracion']->dia_vencimiento,
        'mes_primera_pension' => 3,
        ...$sobrescrituras,
    ]);
}

function crearCuotaB5a(
    array $academia,
    Matricula $matricula,
    string $concepto,
    int $numero,
    string $vencimiento,
    string $monto = '100.00',
    array $sobrescrituras = [],
): Cuota {
    return Cuota::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'matricula_id' => $matricula->id,
        'alumno_id' => $matricula->alumno_id,
        'anio_escolar_id' => $matricula->anio_escolar_id,
        'concepto' => $concepto,
        'numero' => $numero,
        'descripcion' => 'Cuota de prueba '.$numero,
        'monto_original' => $monto,
        'vencimiento' => $vencimiento,
        ...$sobrescrituras,
    ]);
}

function vincularResponsablePagoB5a(array $academia, Alumno $alumno): void
{
    $apoderado = Apoderado::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'nombre' => 'Apoderado',
        'apellido' => 'Ficticio',
    ]);

    DB::table('alumno_apoderado')->insert([
        'colegio_id' => $academia['colegio']->id,
        'alumno_id' => $alumno->id,
        'apoderado_id' => $apoderado->id,
        'parentesco' => 'Madre',
        'telefono' => '900000001',
        'email' => 'familia@'.$academia['colegio']->subdominio.'.test',
        'responsable_pago' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @implements ConsultarAplicacionesPago
 */
class AplicacionesPagoPruebaB5a implements ConsultarAplicacionesPago
{
    /**
     * @param  array<int, list<array{monto_aplicado: int|float|string, anulada_en: mixed}>>  $aplicaciones
     */
    public function __construct(private array $aplicaciones) {}

    /**
     * @param  list<int>  $cuotaIds
     * @return array<int, list<array{monto_aplicado: int|float|string, anulada_en: mixed}>>
     */
    public function paraCuotas(array $cuotaIds): array
    {
        return array_intersect_key($this->aplicaciones, array_fill_keys($cuotaIds, true));
    }
}

/**
 * @param  array<int, list<array{monto_aplicado: int|float|string, anulada_en: mixed}>>  $aplicaciones
 */
function usarAplicacionesPagoB5a(array $aplicaciones): void
{
    app()->instance(ConsultarAplicacionesPago::class, new AplicacionesPagoPruebaB5a($aplicaciones));
}

test('genera matrícula y pensiones al confirmar una matrícula y admite ejecución directa del listener', function () {
    $academia = prepararAcademiaB5a('b5a-confirmar');
    crearTarifaB5a($academia);
    $alumno = crearAlumnoB5a($academia);
    vincularResponsablePagoB5a($academia, $alumno);

    $matricula = app(GestionarMatriculas::class)->crear($academia['usuario'], [
        'alumno_id' => $alumno->id,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'fecha_matricula' => '2026-03-02',
        'estado' => 'matriculado',
    ]);

    app(GenerarCuotasAlConfirmarMatricula::class)->handle(
        new MatriculaConfirmada($matricula),
    );

    $this->assertDatabaseCount('cuotas', 4);
    $this->assertDatabaseHas('cuotas', [
        'matricula_id' => $matricula->id,
        'concepto' => 'matricula',
        'numero' => 1,
        'descripcion' => 'Matrícula 2026',
        'monto_original' => '100.00',
        'vencimiento' => '2026-03-02',
    ]);
    $this->assertDatabaseHas('cuotas', [
        'matricula_id' => $matricula->id,
        'concepto' => 'pension',
        'numero' => 1,
        'descripcion' => 'Pensión marzo 2026',
        'vencimiento' => '2026-03-10',
    ]);

    $this->assertModelExists($matricula);
});

test('una matrícula confirmada sin tarifa sigue vigente y deja auditoría sin cuotas', function () {
    $academia = prepararAcademiaB5a('b5a-sin-tarifa');
    $alumno = crearAlumnoB5a($academia);
    vincularResponsablePagoB5a($academia, $alumno);

    $matricula = app(GestionarMatriculas::class)->crear($academia['usuario'], [
        'alumno_id' => $alumno->id,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'seccion_id' => $academia['seccion']->id,
        'fecha_matricula' => '2026-03-02',
        'estado' => 'matriculado',
    ]);
    app(GenerarCuotasAlConfirmarMatricula::class)->handle(
        new MatriculaConfirmada($matricula),
    );

    $this->assertModelExists($matricula);
    $this->assertDatabaseCount('cuotas', 0);
    $this->assertDatabaseHas('auditorias', [
        'colegio_id' => $academia['colegio']->id,
        'evento' => 'cuotas.generacion_sin_tarifa',
        'entidad' => 'matriculas',
        'entidad_id' => $matricula->id,
    ]);
});

test('crea tarifa con valores predeterminados y genera cuotas de forma idempotente por endpoint', function () {
    $academia = prepararAcademiaB5a('b5a-idempotencia', numeroCuotas: 4, diaVencimiento: 16);
    $this->actingAs($academia['usuario'], 'sanctum');

    $respuestaTarifa = $this->postJson('/api/v1/tarifas', [
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'monto_matricula' => '125.00',
        'monto_pension' => '80.00',
    ])->assertCreated()
        ->assertJsonPath('data.tarifa.numero_cuotas', 4)
        ->assertJsonPath('data.tarifa.dia_vencimiento', 16)
        ->assertJsonPath('data.tarifa.mes_primera_pension', 3);

    $this->assertDatabaseHas('tarifas', [
        'id' => $respuestaTarifa->json('data.tarifa.id'),
        'colegio_id' => $academia['colegio']->id,
        'numero_cuotas' => 4,
        'dia_vencimiento' => 16,
    ]);

    $alumno = crearAlumnoB5a($academia);
    $matricula = crearMatriculaB5a($academia, $alumno);
    $url = "/api/v1/matriculas/{$matricula->id}/generar-cuotas";

    $this->postJson($url)->assertOk()
        ->assertJsonCount(5, 'data.cuotas');
    $this->postJson($url)->assertOk()
        ->assertJsonCount(5, 'data.cuotas');

    $this->assertDatabaseCount('cuotas', 5);
    $this->assertDatabaseHas('cuotas', [
        'matricula_id' => $matricula->id,
        'concepto' => 'pension',
        'numero' => 4,
        'vencimiento' => '2026-06-16',
    ]);
});

test('actualiza y elimina tarifas lógicamente conservando la unicidad por año y grado', function () {
    $academia = prepararAcademiaB5a('b5a-crud-tarifas');
    $this->actingAs($academia['usuario'], 'sanctum');
    $tarifa = $this->postJson('/api/v1/tarifas', [
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'monto_matricula' => '100.00',
        'monto_pension' => '100.00',
    ])->assertCreated()
        ->json('data.tarifa.id');

    $this->putJson("/api/v1/tarifas/{$tarifa}", [
        'monto_matricula' => '125.00',
    ])->assertOk()
        ->assertJsonPath('data.tarifa.monto_matricula', '125.00');

    $this->postJson('/api/v1/tarifas', [
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'monto_matricula' => '100.00',
        'monto_pension' => '100.00',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('grado_id');

    $this->deleteJson("/api/v1/tarifas/{$tarifa}")->assertOk()
        ->assertJsonPath('data', null);

    $this->assertDatabaseCount('tarifas', 1);
    $this->assertDatabaseHas('tarifas', [
        'id' => $tarifa,
        'monto_matricula' => '125.00',
    ]);
    expect(Tarifa::query()->count())->toBe(0)
        ->and(Tarifa::withTrashed()->count())->toBe(1);
});

test('al retirar una matrícula anula solo cuotas futuras sin pagos aplicados', function () {
    $academia = prepararAcademiaB5a('b5a-retiro');
    $alumno = crearAlumnoB5a($academia);
    $matricula = crearMatriculaB5a($academia, $alumno);
    $vencidaAntesDelRetiro = crearCuotaB5a($academia, $matricula, 'pension', 1, '2026-04-30');
    $vencidaEnLaFechaDelRetiro = crearCuotaB5a($academia, $matricula, 'pension', 2, '2026-05-01');
    $futuraSinPago = crearCuotaB5a($academia, $matricula, 'pension', 3, '2026-06-01');
    $futuraParcial = crearCuotaB5a($academia, $matricula, 'pension', 4, '2026-07-01');
    $futuraPagada = crearCuotaB5a($academia, $matricula, 'pension', 5, '2026-08-01');
    usarAplicacionesPagoB5a([
        $futuraParcial->id => [['monto_aplicado' => '10.00', 'anulada_en' => null]],
        $futuraPagada->id => [['monto_aplicado' => '100.00', 'anulada_en' => null]],
    ]);

    $retirada = app(GestionarMatriculas::class)->cambiarEstado($academia['usuario'], $matricula, [
        'estado' => 'retirado',
        'motivo' => 'Retiro de prueba',
        'fecha_retiro' => '2026-05-01',
    ]);
    app(AnularCuotasAlRetirarMatricula::class)->handle(
        new MatriculaRetirada($retirada),
    );

    expect($vencidaAntesDelRetiro->fresh()->anulada_en)->toBeNull()
        ->and($vencidaEnLaFechaDelRetiro->fresh()->anulada_en)->toBeNull()
        ->and($futuraSinPago->fresh()->anulada_en)->not->toBeNull()
        ->and($futuraParcial->fresh()->anulada_en)->toBeNull()
        ->and($futuraPagada->fresh()->anulada_en)->toBeNull();
});

test('deriva RN-01 a RN-04 desde descuentos y aplicaciones de pago no anuladas', function () {
    $this->travelTo('2026-06-01 12:00:00');
    $academia = prepararAcademiaB5a('b5a-estados');
    $alumno = crearAlumnoB5a($academia);
    $matricula = crearMatriculaB5a($academia, $alumno);
    $cuotaVencida = crearCuotaB5a($academia, $matricula, 'pension', 1, '2026-02-01');
    $cuotaPendiente = crearCuotaB5a($academia, $matricula, 'pension', 2, '2026-12-01');
    $cuotaParcial = crearCuotaB5a($academia, $matricula, 'pension', 3, '2026-12-02');
    $cuotaPagada = crearCuotaB5a($academia, $matricula, 'pension', 4, '2026-12-03');
    $cuotaAnulada = crearCuotaB5a($academia, $matricula, 'pension', 5, '2026-12-04', sobrescrituras: [
        'anulada_en' => '2026-11-01 10:00:00',
        'motivo_anulacion' => 'Anulación ficticia',
    ]);
    Descuento::query()->create([
        'colegio_id' => $academia['colegio']->id,
        'cuota_id' => $cuotaParcial->id,
        'tipo' => 'beca',
        'modo' => 'monto',
        'valor' => '10.00',
        'monto_calculado' => '10.00',
        'motivo' => 'Beca ficticia',
        'usuario_id' => $academia['usuario']->id,
    ]);
    usarAplicacionesPagoB5a([
        $cuotaParcial->id => [
            ['monto_aplicado' => '30.00', 'anulada_en' => null],
            ['monto_aplicado' => '5.00', 'anulada_en' => '2026-11-01 10:00:00'],
        ],
        $cuotaPagada->id => [['monto_aplicado' => '100.00', 'anulada_en' => null]],
        $cuotaAnulada->id => [],
    ]);
    $this->actingAs($academia['usuario'], 'sanctum');

    $this->assertFalse(Schema::hasColumn('cuotas', 'estado'));

    $respuesta = $this->getJson('/api/v1/cuotas?per_page=10')->assertOk();
    $cuotas = collect($respuesta->json('data.cuotas'))->keyBy('id');

    expect($cuotas[$cuotaVencida->id]['estado'])->toBe('vencida')
        ->and($cuotas[$cuotaPendiente->id]['estado'])->toBe('pendiente')
        ->and($cuotas[$cuotaParcial->id]['estado'])->toBe('parcial')
        ->and($cuotas[$cuotaParcial->id]['saldo'])->toBe('60.00')
        ->and($cuotas[$cuotaParcial->id]['total_descuentos'])->toBe('10.00')
        ->and($cuotas[$cuotaPagada->id]['estado'])->toBe('pagada')
        ->and($cuotas[$cuotaAnulada->id]['estado'])->toBe('anulada');

    $this->getJson('/api/v1/cuotas?estado=parcial&concepto=pension')
        ->assertOk()
        ->assertJsonCount(1, 'data.cuotas')
        ->assertJsonPath('data.cuotas.0.id', $cuotaParcial->id);
});

test('filtra, ordena y pagina cuotas en el colegio de la sesión', function () {
    $this->travelTo('2026-02-01 12:00:00');
    $academia = prepararAcademiaB5a('b5a-filtros');
    $alumno = crearAlumnoB5a($academia);
    $otroAlumno = crearAlumnoB5a($academia, 2);
    $matricula = crearMatriculaB5a($academia, $alumno);
    $otraMatricula = crearMatriculaB5a($academia, $otroAlumno);
    crearCuotaB5a($academia, $matricula, 'pension', 1, '2026-03-10');
    $cuotaAbril = crearCuotaB5a($academia, $matricula, 'pension', 2, '2026-04-10');
    crearCuotaB5a($academia, $matricula, 'pension', 3, '2026-05-10');
    crearCuotaB5a($academia, $otraMatricula, 'pension', 1, '2026-04-15');
    $this->actingAs($academia['usuario'], 'sanctum');

    $this->getJson('/api/v1/cuotas?'
        .'alumno_id='.$alumno->id
        .'&matricula_id='.$matricula->id
        .'&anio_escolar_id='.$academia['anio']->id
        .'&concepto=pension'
        .'&vencimiento_desde=2026-03-01'
        .'&vencimiento_hasta=2026-04-30'
        .'&orden=desc'
        .'&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.cuotas')
        ->assertJsonPath('data.cuotas.0.id', $cuotaAbril->id)
        ->assertJsonPath('data.paginacion.total', 2)
        ->assertJsonPath('data.paginacion.por_pagina', 1)
        ->assertJsonPath('data.paginacion.ultima_pagina', 2);

    $this->getJson("/api/v1/matriculas/{$matricula->id}/cuotas")
        ->assertOk()
        ->assertJsonCount(3, 'data.cuotas')
        ->assertJsonPath('data.paginacion.total', 3);

    $this->getJson('/api/v1/cuotas?sort=colegio_id')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort');
});

test('aplica descuentos por monto y porcentaje, limita el total y los anula sin borrar', function () {
    $academia = prepararAcademiaB5a('b5a-descuentos');
    $alumno = crearAlumnoB5a($academia);
    $matricula = crearMatriculaB5a($academia, $alumno);
    $cuota = crearCuotaB5a($academia, $matricula, 'pension', 1, '2026-12-10');
    $this->actingAs($academia['usuario'], 'sanctum');

    $monto = $this->postJson("/api/v1/cuotas/{$cuota->id}/descuentos", [
        'tipo' => 'beca',
        'modo' => 'monto',
        'valor' => '40.00',
        'motivo' => 'Beca ficticia',
    ])->assertCreated()
        ->assertJsonPath('data.descuento.monto_calculado', '40.00')
        ->json('data.descuento.id');

    $porcentaje = $this->postJson("/api/v1/cuotas/{$cuota->id}/descuentos", [
        'tipo' => 'descuento',
        'modo' => 'porcentaje',
        'valor' => '50.00',
        'motivo' => 'Descuento ficticio',
    ])->assertCreated()
        ->assertJsonPath('data.descuento.monto_calculado', '50.00')
        ->json('data.descuento.id');

    $this->postJson("/api/v1/cuotas/{$cuota->id}/descuentos", [
        'tipo' => 'exoneracion',
        'modo' => 'monto',
        'valor' => '20.00',
        'motivo' => 'Exoneración ficticia',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('valor');

    $this->postJson("/api/v1/cuotas/{$cuota->id}/descuentos", [
        'tipo' => 'descuento',
        'modo' => 'porcentaje',
        'valor' => '101.00',
        'motivo' => 'Porcentaje fuera de rango',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('valor');

    $this->postJson("/api/v1/descuentos/{$monto}/anular", [
        'motivo' => 'Corrección ficticia',
    ])->assertOk()
        ->assertJsonPath('data.descuento.id', $monto);

    $this->getJson('/api/v1/cuotas?matricula_id='.$matricula->id)
        ->assertOk()
        ->assertJsonPath('data.cuotas.0.saldo', '50.00')
        ->assertJsonPath('data.cuotas.0.total_descuentos', '50.00');

    $this->assertDatabaseHas('descuentos', [
        'id' => $monto,
        'colegio_id' => $academia['colegio']->id,
        'motivo' => 'Beca ficticia',
        'anulado_por_usuario_id' => $academia['usuario']->id,
    ]);
    $this->assertDatabaseHas('descuentos', [
        'id' => $porcentaje,
        'monto_calculado' => '50.00',
        'anulada_en' => null,
    ]);

    $auditoriaAnulacion = DB::table('auditorias')
        ->where('evento', 'descuentos.actualizado')
        ->where('entidad_id', $monto)
        ->first();

    expect($auditoriaAnulacion)->not->toBeNull()
        ->and($auditoriaAnulacion->valores_nuevos)->not->toContain('Corrección ficticia');

    $cuotaParcial = crearCuotaB5a($academia, $matricula, 'pension', 2, '2026-12-11');
    usarAplicacionesPagoB5a([
        $cuotaParcial->id => [['monto_aplicado' => '95.00', 'anulada_en' => null]],
    ]);

    $this->postJson("/api/v1/cuotas/{$cuotaParcial->id}/descuentos", [
        'tipo' => 'descuento',
        'modo' => 'monto',
        'valor' => '10.00',
        'motivo' => 'Supera el saldo pendiente',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('valor');

    $this->assertDatabaseCount('descuentos', 2);
});

test('aplica descuentos de matrícula solo a pensiones pendientes o parciales con saldo', function () {
    $academia = prepararAcademiaB5a('b5a-descuentos-matricula');
    $alumno = crearAlumnoB5a($academia);
    $matricula = crearMatriculaB5a($academia, $alumno);
    $pendiente = crearCuotaB5a($academia, $matricula, 'pension', 1, '2026-12-10');
    $parcial = crearCuotaB5a($academia, $matricula, 'pension', 2, '2026-12-11');
    $pagada = crearCuotaB5a($academia, $matricula, 'pension', 3, '2026-12-12');
    $anulada = crearCuotaB5a($academia, $matricula, 'pension', 4, '2026-12-13', sobrescrituras: [
        'anulada_en' => '2026-11-01 10:00:00',
        'motivo_anulacion' => 'Anulación ficticia',
    ]);
    crearCuotaB5a($academia, $matricula, 'matricula', 1, '2026-03-02');
    usarAplicacionesPagoB5a([
        $parcial->id => [['monto_aplicado' => '25.00', 'anulada_en' => null]],
        $pagada->id => [['monto_aplicado' => '100.00', 'anulada_en' => null]],
    ]);
    $this->actingAs($academia['usuario'], 'sanctum');

    $respuesta = $this->postJson("/api/v1/matriculas/{$matricula->id}/descuentos", [
        'tipo' => 'beca',
        'modo' => 'monto',
        'valor' => '10.00',
        'motivo' => 'Beca grupal ficticia',
    ])->assertCreated()
        ->assertJsonCount(2, 'data.descuentos');

    expect($respuesta->json('data.descuentos.*.cuota_id'))
        ->toEqualCanonicalizing([$pendiente->id, $parcial->id]);
    $this->assertDatabaseCount('descuentos', 2);
});

test('respeta los permisos por rol y aísla tarifas, cuotas y descuentos entre colegios', function () {
    $academiaA = prepararAcademiaB5a('b5a-tenant-a');
    $academiaB = prepararAcademiaB5a('b5a-tenant-b');
    crearTarifaB5a($academiaA);
    $tarifaB = crearTarifaB5a($academiaB);
    $alumnoA = crearAlumnoB5a($academiaA);
    $alumnoB = crearAlumnoB5a($academiaB);
    $matriculaA = crearMatriculaB5a($academiaA, $alumnoA);
    $matriculaB = crearMatriculaB5a($academiaB, $alumnoB);
    $cuotaA = crearCuotaB5a($academiaA, $matriculaA, 'pension', 1, '2026-12-10');
    $cuotaB = crearCuotaB5a($academiaB, $matriculaB, 'pension', 1, '2026-12-10');
    $descuentoB = Descuento::query()->create([
        'colegio_id' => $academiaB['colegio']->id,
        'cuota_id' => $cuotaB->id,
        'tipo' => 'beca',
        'modo' => 'monto',
        'valor' => '10.00',
        'monto_calculado' => '10.00',
        'motivo' => 'Beca ficticia',
        'usuario_id' => $academiaB['usuario']->id,
    ]);

    $roles = [
        'Director' => [true, true, true, true],
        'Administrador' => [true, true, true, false],
        'Tesorería' => [true, false, true, false],
        'Docente' => [false, false, false, false],
        'Auxiliar' => [false, false, false, false],
        'Apoderado' => [false, false, false, false],
    ];

    $usuariosPorRol = [];

    foreach ($roles as $rol => [$verTarifas, $gestionarTarifas, $verCuotas, $aprobarDescuentos]) {
        $permisos = [
            'tarifas.ver',
            'tarifas.gestionar',
            'pagos.ver',
            'descuentos.aprobar',
        ];
        $usuario = crearUsuarioB5a($academiaA['colegio'], 'b5a-tenant-a', $rol, $permisos);
        $usuariosPorRol[$rol] = $usuario;
        setPermissionsTeamId($academiaA['colegio']->id);

        expect(app(TarifaPolicy::class)->viewAny($usuario))->toBe($verTarifas)
            ->and(app(TarifaPolicy::class)->create($usuario))->toBe($gestionarTarifas)
            ->and(app(CuotaPolicy::class)->viewAny($usuario))->toBe($verCuotas)
            ->and(app(DescuentoPolicy::class)->applyTo($usuario, $cuotaB))->toBeFalse()
            ->and(app(DescuentoPolicy::class)->applyTo($usuario, $cuotaA))->toBe($aprobarDescuentos);

        setPermissionsTeamId(null);
    }

    $this->actingAs($academiaA['usuario'], 'sanctum');
    $this->getJson('/api/v1/tarifas')->assertOk()->assertJsonCount(1, 'data.tarifas');
    $this->getJson('/api/v1/cuotas')->assertOk()->assertJsonCount(1, 'data.cuotas');
    $this->getJson("/api/v1/tarifas/{$tarifaB->id}")->assertNotFound();
    $this->putJson("/api/v1/tarifas/{$tarifaB->id}", [
        'monto_matricula' => '120.00',
    ])->assertNotFound();
    $this->assertDatabaseHas('tarifas', [
        'id' => $tarifaB->id,
        'monto_matricula' => '100.00',
    ]);
    $this->getJson('/api/v1/cuotas?matricula_id='.$matriculaB->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('matricula_id');
    $this->postJson("/api/v1/cuotas/{$cuotaB->id}/descuentos", [
        'tipo' => 'beca',
        'modo' => 'monto',
        'valor' => '10.00',
        'motivo' => 'No autorizado',
    ])->assertNotFound();
    $this->postJson("/api/v1/descuentos/{$descuentoB->id}/anular", [
        'motivo' => 'No autorizado',
    ])->assertNotFound();

    $this->actingAs($usuariosPorRol['Docente'], 'sanctum');
    $this->getJson('/api/v1/tarifas')->assertForbidden();
    $this->getJson('/api/v1/cuotas')->assertForbidden();
});

test('rechaza tarifas fuera de rango, claves del colegio y cambios de años escolares cerrados', function () {
    $academia = prepararAcademiaB5a('b5a-validaciones');
    $this->actingAs($academia['usuario'], 'sanctum');

    $this->postJson('/api/v1/tarifas', [
        'colegio_id' => $academia['colegio']->id,
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'monto_matricula' => '-1.00',
        'monto_pension' => '100.00',
        'numero_cuotas' => 13,
        'dia_vencimiento' => 29,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['colegio_id', 'monto_matricula', 'numero_cuotas', 'dia_vencimiento']);

    $this->postJson('/api/v1/tarifas', [
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'monto_matricula' => '100000000.00',
        'monto_pension' => '100.00',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('monto_matricula');

    $tarifa = app(GestionarTarifas::class)->crear($academia['usuario'], [
        'anio_escolar_id' => $academia['anio']->id,
        'grado_id' => $academia['grado']->id,
        'monto_matricula' => '100.00',
        'monto_pension' => '100.00',
    ]);
    $academia['anio']->forceFill(['cerrado' => true])->save();

    $this->putJson("/api/v1/tarifas/{$tarifa->id}", [
        'monto_matricula' => '110.00',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('anio_escolar_id');
    $this->deleteJson("/api/v1/tarifas/{$tarifa->id}")->assertUnprocessable();
    $this->assertDatabaseHas('tarifas', [
        'id' => $tarifa->id,
        'deleted_at' => null,
    ]);
});

test('siembra tarifas por grado y cuotas para las matrículas ficticias de Salesian Innova 2026', function () {
    $this->seed(DatabaseSeeder::class);
    $colegio = Colegio::query()->where('subdominio', 'salesian-innova')->firstOrFail();
    $anio = AnioEscolar::query()
        ->where('colegio_id', $colegio->id)
        ->where('nombre', '2026')
        ->firstOrFail();
    $matricula = Matricula::query()
        ->where('colegio_id', $colegio->id)
        ->where('anio_escolar_id', $anio->id)
        ->where('estado', 'matriculado')
        ->firstOrFail();
    $director = Usuario::query()->where('email', 'director@salesian-innova.test')->firstOrFail();
    $administrador = Usuario::query()->where('email', 'administrador@salesian-innova.test')->firstOrFail();
    $tesoreria = Usuario::query()->where('email', 'tesoreria@salesian-innova.test')->firstOrFail();
    setPermissionsTeamId($colegio->id);

    expect(Tarifa::query()->where('colegio_id', $colegio->id)->where('anio_escolar_id', $anio->id)->count())
        ->toBe(14)
        ->and(Cuota::query()->where('colegio_id', $colegio->id)->where('matricula_id', $matricula->id)->count())
        ->toBe(11)
        ->and(Cuota::query()->where('colegio_id', $colegio->id)->count())
        ->toBe(440)
        ->and($director->hasPermissionTo('tarifas.gestionar', 'web'))->toBeTrue()
        ->and($administrador->hasPermissionTo('pagos.ver', 'web'))->toBeTrue()
        ->and($administrador->hasPermissionTo('descuentos.aprobar', 'web'))->toBeFalse()
        ->and($tesoreria->hasPermissionTo('tarifas.ver', 'web'))->toBeTrue();
    setPermissionsTeamId(null);
});
