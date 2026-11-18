<?php

use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\AplicacionPago;
use App\Models\Apoderado;
use App\Models\Auditoria;
use App\Models\Colegio;
use App\Models\ConfiguracionColegio;
use App\Models\Cuota;
use App\Models\GestionCobranza;
use App\Models\Grado;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Pago;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-14 12:00:00');
});

/**
 * @return array{
 *   colegio: Colegio,
 *   usuario: Usuario,
 *   anio: AnioEscolar,
 *   nivel: Nivel,
 *   grado: Grado,
 *   seccion: Seccion,
 *   alumno: Alumno,
 *   matricula: Matricula,
 *   cuotas: list<Cuota>
 * }
 */
function prepararEscenarioB6(string $nombre, string $rol = 'Director', ?array $permisos = null): array
{
    auth()->logout();
    setPermissionsTeamId(null);
    $colegio = Colegio::query()->create([
        'nombre' => 'Colegio '.$nombre,
        'subdominio' => $nombre,
        'activo' => true,
    ]);
    $permisos ??= match ($rol) {
        'Director' => ['deudas.ver', 'reportes.ver', 'cobranza.gestionar'],
        'Administrador' => ['deudas.ver', 'reportes.ver'],
        'Tesorería' => ['deudas.ver', 'reportes.ver', 'cobranza.gestionar'],
        'Coordinación' => ['deudas.ver', 'reportes.ver', 'cobranza.gestionar'],
        default => [],
    };
    $usuario = Usuario::query()->create([
        'nombre' => $rol,
        'apellido' => 'Ficticio',
        'email' => $nombre.'@prueba.test',
        'password' => Hash::make('Password123!'),
        'activo' => true,
    ]);
    $usuario->forceFill(['colegio_id' => $colegio->id])->save();

    foreach (array_unique([...$permisos, 'deudas.ver', 'reportes.ver', 'cobranza.gestionar']) as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    setPermissionsTeamId($colegio->id);
    $rolModelo = Role::findOrCreate($rol, 'web');
    $rolModelo->syncPermissions($permisos);
    $usuario->assignRole($rolModelo);
    setPermissionsTeamId(null);

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
    ConfiguracionColegio::query()->create([
        'colegio_id' => $colegio->id,
        'numero_cuotas_pension' => 10,
        'dia_vencimiento' => 10,
        'dias_alerta_cuotas' => 5,
        'serie_recibos' => 'R001',
        'frecuencia_registro_avance' => 'semanal',
        'recargo_mora_activo' => false,
    ]);
    $alumno = Alumno::query()->create([
        'colegio_id' => $colegio->id,
        'nombre' => 'Lucía',
        'apellido' => 'Ficticia',
        'tipo_documento' => 'DNI',
        'documento_identidad' => 'B6'.str_pad((string) $colegio->id, 8, '0', STR_PAD_LEFT),
        'fecha_nacimiento' => '2015-03-15',
        'sexo' => 'F',
        'direccion' => 'Dirección ficticia',
        'estado' => 'activo',
        'activo' => true,
    ]);
    $apoderado = Apoderado::query()->create([
        'colegio_id' => $colegio->id,
        'nombre' => 'María',
        'apellido' => 'Responsable',
    ]);
    $alumno->apoderados()->attach($apoderado->id, [
        'colegio_id' => $colegio->id,
        'parentesco' => 'Madre',
        'telefono' => '900000001',
        'email' => 'responsable@ejemplo.test',
        'responsable_pago' => true,
    ]);
    $matricula = Matricula::query()->create([
        'colegio_id' => $colegio->id,
        'alumno_id' => $alumno->id,
        'anio_escolar_id' => $anio->id,
        'grado_id' => $grado->id,
        'seccion_id' => $seccion->id,
        'usuario_id' => $usuario->id,
        'anio_escolar' => $anio->nombre,
        'grado' => $grado->nombre,
        'seccion' => $seccion->nombre,
        'estado' => 'pendiente_documentos',
        'fecha_matricula' => today()->toDateString(),
    ]);
    $cuotas = [
        Cuota::query()->create([
            'colegio_id' => $colegio->id,
            'matricula_id' => $matricula->id,
            'alumno_id' => $alumno->id,
            'anio_escolar_id' => $anio->id,
            'concepto' => 'pension',
            'numero' => 1,
            'descripcion' => 'Pensión vencida ficticia',
            'monto_original' => '100.00',
            'vencimiento' => today()->subDays(10)->toDateString(),
        ]),
        Cuota::query()->create([
            'colegio_id' => $colegio->id,
            'matricula_id' => $matricula->id,
            'alumno_id' => $alumno->id,
            'anio_escolar_id' => $anio->id,
            'concepto' => 'pension',
            'numero' => 2,
            'descripcion' => 'Pensión por vencer ficticia',
            'monto_original' => '50.00',
            'vencimiento' => today()->addDays(3)->toDateString(),
        ]),
        Cuota::query()->create([
            'colegio_id' => $colegio->id,
            'matricula_id' => $matricula->id,
            'alumno_id' => $alumno->id,
            'anio_escolar_id' => $anio->id,
            'concepto' => 'pension',
            'numero' => 3,
            'descripcion' => 'Pensión futura ficticia',
            'monto_original' => '30.00',
            'vencimiento' => today()->addDays(30)->toDateString(),
        ]),
    ];

    return compact('colegio', 'usuario', 'anio', 'nivel', 'grado', 'seccion', 'alumno', 'matricula', 'cuotas');
}

/**
 * @param  array<string, mixed>  $escenario
 */
function agregarAlumnoConDeudaB6(array $escenario, int $correlativo, ?string $vencimiento = null): void
{
    $alumno = Alumno::query()->create([
        'colegio_id' => $escenario['colegio']->id,
        'nombre' => 'Alumno',
        'apellido' => 'Deudor '.$correlativo,
        'tipo_documento' => 'DNI',
        'documento_identidad' => 'B6'.$escenario['colegio']->id.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT),
        'fecha_nacimiento' => '2015-03-15',
        'sexo' => 'M',
        'estado' => 'activo',
        'activo' => true,
    ]);
    $matricula = Matricula::query()->create([
        'colegio_id' => $escenario['colegio']->id,
        'alumno_id' => $alumno->id,
        'anio_escolar_id' => $escenario['anio']->id,
        'grado_id' => $escenario['grado']->id,
        'seccion_id' => $escenario['seccion']->id,
        'usuario_id' => $escenario['usuario']->id,
        'anio_escolar' => '2026',
        'grado' => '1ro',
        'seccion' => 'A',
        'estado' => 'matriculado',
        'fecha_matricula' => today()->toDateString(),
    ]);
    Cuota::query()->create([
        'colegio_id' => $escenario['colegio']->id,
        'matricula_id' => $matricula->id,
        'alumno_id' => $alumno->id,
        'anio_escolar_id' => $escenario['anio']->id,
        'concepto' => 'pension',
        'numero' => 1,
        'descripcion' => 'Cuota vencida ficticia',
        'monto_original' => '40.00',
        'vencimiento' => $vencimiento ?? today()->subDays(2)->toDateString(),
    ]);
}

/**
 * @param  array<string, mixed>  $escenario
 */
function registrarPagoReporteB6(array $escenario): void
{
    $pago = Pago::query()->create([
        'colegio_id' => $escenario['colegio']->id,
        'matricula_id' => $escenario['matricula']->id,
        'alumno_id' => $escenario['alumno']->id,
        'usuario_id' => $escenario['usuario']->id,
        'fecha_pago' => today()->toDateString(),
        'monto' => '20.00',
        'medio' => 'efectivo',
        'estado' => 'vigente',
        'serie_constancia' => 'R001',
        'numero_constancia' => 1,
        'constancia' => 'R001-000001',
    ]);
    AplicacionPago::query()->create([
        'colegio_id' => $escenario['colegio']->id,
        'pago_id' => $pago->id,
        'cuota_id' => $escenario['cuotas'][0]->id,
        'monto_aplicado' => '20.00',
    ]);
}

it('lista solo alumnos con cuotas vencidas y devuelve saldo pendiente y contacto del responsable', function (): void {
    $escenario = prepararEscenarioB6('deudas-listado');
    agregarAlumnoConDeudaB6($escenario, 1, today()->addDays(2)->toDateString());
    $this->actingAs($escenario['usuario']);

    $this->getJson('/api/v1/deudas?per_page=10')
        ->assertOk()
        ->assertJsonPath('data.paginacion.total', 1)
        ->assertJsonPath('data.deudas.0.alumno_id', $escenario['alumno']->id)
        ->assertJsonPath('data.deudas.0.total_vencido', '100.00')
        ->assertJsonPath('data.deudas.0.total_pendiente', '180.00')
        ->assertJsonPath('data.deudas.0.cuotas_vencidas', 1)
        ->assertJsonPath('data.deudas.0.dias_atraso', 10)
        ->assertJsonPath('data.deudas.0.apoderado_responsable_pago.telefono', '900000001');
});

it('aplica filtros de antigüedad, monto, sección, año escolar y búsqueda de contacto en deudas', function (): void {
    $escenario = prepararEscenarioB6('deudas-filtros');
    $this->actingAs($escenario['usuario']);

    $this->getJson('/api/v1/deudas?seccion_id='.$escenario['seccion']->id.'&anio_escolar_id='.$escenario['anio']->id.'&dias_atraso_min=11')
        ->assertOk()
        ->assertJsonPath('data.paginacion.total', 0);
    $this->getJson('/api/v1/deudas?nivel_id='.$escenario['nivel']->id.'&grado_id='.$escenario['grado']->id.'&monto_min=99.99&buscar=Responsable')
        ->assertOk()
        ->assertJsonPath('data.paginacion.total', 1);
    $this->getJson('/api/v1/deudas?buscar=nombre-que-no-existe')
        ->assertOk()
        ->assertJsonPath('data.paginacion.total', 0);
});

it('agrupa las deudas por aula y exporta PDF y XLSX auditando cada exportación', function (): void {
    $escenario = prepararEscenarioB6('deudas-aula');
    $this->actingAs($escenario['usuario']);

    $this->getJson('/api/v1/deudas/por-aula')
        ->assertOk()
        ->assertJsonPath('data.secciones.0.seccion', 'A')
        ->assertJsonPath('data.secciones.0.alumnos.0.total_vencido', '100.00');
    $this->get('/api/v1/deudas/por-aula?formato=pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->get('/api/v1/deudas/por-aula?formato=xlsx')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $auditorias = Auditoria::query()
        ->where('colegio_id', $escenario['colegio']->id)
        ->where('evento', 'reportes.exportados')
        ->orderBy('id')
        ->get();

    expect($auditorias)->toHaveCount(2);
    expect($auditorias->pluck('usuario_id')->unique()->all())->toBe([$escenario['usuario']->id]);
    expect($auditorias->pluck('valores_nuevos')->all())->toBe([
        ['reporte' => 'deudas_aula', 'formato' => 'pdf'],
        ['reporte' => 'deudas_aula', 'formato' => 'xlsx'],
    ]);
});

it('registra gestiones de cobranza auditadas y las consulta por alumno', function (): void {
    $escenario = prepararEscenarioB6('gestiones-cobranza');
    $this->actingAs($escenario['usuario']);

    $respuesta = $this->postJson('/api/v1/alumnos/'.$escenario['alumno']->id.'/gestiones-cobranza', [
        'fecha' => today()->toDateString(),
        'medio_contacto' => 'whatsapp',
        'resultado' => 'Se acordó una fecha de pago',
        'compromiso_pago_fecha' => today()->addDays(5)->toDateString(),
        'compromiso_monto' => '50.00',
        'observacion' => 'Seguimiento ficticio.',
    ]);

    $respuesta->assertCreated()
        ->assertJsonPath('data.gestion.usuario_id', $escenario['usuario']->id)
        ->assertJsonPath('data.gestion.medio_contacto', 'whatsapp');
    $this->assertDatabaseHas('gestiones_cobranza', [
        'colegio_id' => $escenario['colegio']->id,
        'alumno_id' => $escenario['alumno']->id,
        'resultado' => 'Se acordó una fecha de pago',
    ]);
    $this->assertDatabaseHas('auditorias', [
        'colegio_id' => $escenario['colegio']->id,
        'evento' => 'cobranza.gestion_registrada',
    ]);
    $this->getJson('/api/v1/alumnos/'.$escenario['alumno']->id.'/gestiones-cobranza')
        ->assertOk()
        ->assertJsonPath('data.gestiones.0.id', $respuesta->json('data.gestion.id'));
});

it('rechaza medios de contacto inválidos y oculta gestiones de otro colegio', function (): void {
    $colegioA = prepararEscenarioB6('gestion-aislamiento-a');
    $colegioB = prepararEscenarioB6('gestion-aislamiento-b');
    $this->actingAs($colegioA['usuario']);

    $this->postJson('/api/v1/alumnos/'.$colegioA['alumno']->id.'/gestiones-cobranza', [
        'fecha' => today()->toDateString(),
        'medio_contacto' => 'sms',
        'resultado' => 'No permitido',
    ])->assertUnprocessable()->assertJsonValidationErrors('medio_contacto');
    $this->getJson('/api/v1/alumnos/'.$colegioB['alumno']->id.'/gestiones-cobranza')->assertNotFound();
});

it('resume cuotas vencidas y por vencer según los días configurados', function (): void {
    $escenario = prepararEscenarioB6('alertas-cuotas');
    $this->actingAs($escenario['usuario']);

    $this->getJson('/api/v1/alertas/cuotas')
        ->assertOk()
        ->assertJsonPath('data.dias_configurados', 5)
        ->assertJsonPath('data.por_vencer.cantidad', 1)
        ->assertJsonPath('data.por_vencer.monto', '50.00')
        ->assertJsonPath('data.vencidas.cantidad', 1)
        ->assertJsonPath('data.vencidas.monto', '100.00');

    ConfiguracionColegio::query()
        ->where('colegio_id', $escenario['colegio']->id)
        ->update(['dias_alerta_cuotas' => 2]);

    $this->getJson('/api/v1/alertas/cuotas')
        ->assertOk()
        ->assertJsonPath('data.dias_configurados', 2)
        ->assertJsonPath('data.por_vencer.cantidad', 0);
});

it('exporta todos los reportes en JSON, PDF y XLSX con autorización', function (
    string $ruta,
    string $formato,
    string $tipoContenido,
): void {
    $escenario = prepararEscenarioB6('reporte-'.str_replace(['/', '?', '='], '-', $ruta.$formato));
    registrarPagoReporteB6($escenario);
    $this->actingAs($escenario['usuario']);
    $filtros = $ruta === '/reportes/dashboard' ? '' : '&desde=2026-01-01&hasta=2026-12-31';
    $respuesta = $this->get('/api/v1'.$ruta.'?formato='.$formato.$filtros);

    $respuesta->assertOk()->assertHeader('content-type', $tipoContenido);

    if ($formato === 'json') {
        $ruta === '/reportes/dashboard'
            ? $respuesta->assertJsonPath('data.filtros.mes', '2026-11')
            : $respuesta->assertJsonPath('data.filtros.desde', '2026-01-01');
    } else {
        $this->assertDatabaseHas('auditorias', [
            'colegio_id' => $escenario['colegio']->id,
            'evento' => 'reportes.exportados',
        ]);
    }
})->with([
    'matrículas JSON' => ['/reportes/matriculas', 'json', 'application/json'],
    'matrículas PDF' => ['/reportes/matriculas', 'pdf', 'application/pdf'],
    'matrículas XLSX' => ['/reportes/matriculas', 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'pagos JSON' => ['/reportes/pagos', 'json', 'application/json'],
    'pagos PDF' => ['/reportes/pagos', 'pdf', 'application/pdf'],
    'pagos XLSX' => ['/reportes/pagos', 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'ingresos JSON' => ['/reportes/ingresos-mensuales', 'json', 'application/json'],
    'ingresos PDF' => ['/reportes/ingresos-mensuales', 'pdf', 'application/pdf'],
    'ingresos XLSX' => ['/reportes/ingresos-mensuales', 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'dashboard JSON' => ['/reportes/dashboard', 'json', 'application/json'],
    'dashboard PDF' => ['/reportes/dashboard', 'pdf', 'application/pdf'],
    'dashboard XLSX' => ['/reportes/dashboard', 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);

it('calcula el contenido de matrículas, pagos, ingresos mensuales y dashboard', function (): void {
    $escenario = prepararEscenarioB6('reportes-calculos');
    registrarPagoReporteB6($escenario);
    $this->actingAs($escenario['usuario']);

    $this->getJson('/api/v1/reportes/matriculas')
        ->assertOk()
        ->assertJsonPath('data.resumen.total_matriculas', 1)
        ->assertJsonPath('data.resumen.pendientes_completar', 1);
    $this->getJson('/api/v1/reportes/pagos?desde=2026-11-01&hasta=2026-11-30&medio=efectivo&usuario_id='.$escenario['usuario']->id)
        ->assertOk()
        ->assertJsonPath('data.resumen.cantidad', 1)
        ->assertJsonPath('data.resumen.total_vigente', '20.00')
        ->assertJsonPath('data.filtros.medio', 'efectivo');
    $this->getJson('/api/v1/reportes/ingresos-mensuales?desde=2026-01-01&hasta=2026-12-31')
        ->assertOk()
        ->assertJsonPath('data.filas.0.mes', '2026-11')
        ->assertJsonPath('data.filas.0.cuotas_emitidas', '150.00')
        ->assertJsonPath('data.filas.0.cuotas_cobradas', '20.00')
        ->assertJsonPath('data.filas.1.mes', '2026-12')
        ->assertJsonPath('data.filas.1.cuotas_emitidas', '30.00');
    $escenario['matricula']->update(['estado' => 'matriculado']);
    $this->getJson('/api/v1/reportes/dashboard')
        ->assertOk()
        ->assertJsonPath('data.resumen.matriculados_por_nivel.0.matriculados', 1)
        ->assertJsonPath('data.resumen.recaudacion_mes', '20.00')
        ->assertJsonPath('data.resumen.deuda_total', '160.00')
        ->assertJsonPath('data.resumen.deuda_vencida', '80.00')
        ->assertJsonPath('data.resumen.porcentaje_morosidad', '44.44');
});

it('limita los reportes de Coordinación a matrículas y deuda por aula', function (): void {
    $escenario = prepararEscenarioB6('reportes-coordinacion', 'Coordinación');
    $this->actingAs($escenario['usuario']);

    $this->getJson('/api/v1/reportes/matriculas')->assertOk();
    $this->getJson('/api/v1/reportes/pagos')->assertForbidden();
    $this->getJson('/api/v1/reportes/dashboard')->assertForbidden();
    $this->getJson('/api/v1/deudas/por-aula')->assertOk();
});

it('deniega deudas y cobranza a roles sin esos permisos', function (): void {
    $escenario = prepararEscenarioB6('deudas-docente', 'Docente');
    $this->actingAs($escenario['usuario']);

    $this->getJson('/api/v1/deudas')->assertForbidden();
    $this->postJson('/api/v1/alumnos/'.$escenario['alumno']->id.'/gestiones-cobranza', [
        'fecha' => today()->toDateString(),
        'medio_contacto' => 'llamada',
        'resultado' => 'Intento de prueba',
    ])->assertForbidden();
});

it('autoriza deudas, cobranza y reportes según la matriz de roles', function (
    string $rol,
    bool $puedeVerDeudas,
    bool $puedeGestionarCobranza,
    bool $puedeVerMatriculas,
    bool $puedeVerPagos,
): void {
    $escenario = prepararEscenarioB6('matriz-'.str_replace(' ', '-', $rol), $rol);
    setPermissionsTeamId($escenario['colegio']->id);

    $gate = Gate::forUser($escenario['usuario']);
    expect($gate->allows('ver-deudas'))->toBe($puedeVerDeudas);
    expect($gate->allows('viewAny', GestionCobranza::class))->toBe($puedeGestionarCobranza);
    expect($gate->allows('ver-reporte', 'matriculas'))->toBe($puedeVerMatriculas);
    expect($gate->allows('ver-reporte', 'pagos'))->toBe($puedeVerPagos);

    setPermissionsTeamId(null);
})->with([
    'Director' => ['Director', true, true, true, true],
    'Administrador' => ['Administrador', true, false, true, true],
    'Tesorería' => ['Tesorería', true, true, true, true],
    'Coordinación' => ['Coordinación', true, true, true, false],
    'Docente' => ['Docente', false, false, false, false],
    'Auxiliar' => ['Auxiliar', false, false, false, false],
    'Apoderado' => ['Apoderado', false, false, false, false],
]);

it('requiere autenticación para consultar deudas', function (): void {
    auth()->logout();

    $this->getJson('/api/v1/deudas')->assertUnauthorized();
});

it('no revela deudas de otro colegio y filtra los resultados por colegio', function (): void {
    $colegioA = prepararEscenarioB6('deudas-colegio-a');
    $colegioB = prepararEscenarioB6('deudas-colegio-b');
    $this->actingAs($colegioA['usuario']);

    $this->getJson('/api/v1/deudas?buscar=Lucía')
        ->assertOk()
        ->assertJsonPath('data.paginacion.total', 1)
        ->assertJsonPath('data.deudas.0.alumno_id', $colegioA['alumno']->id);
    $this->getJson('/api/v1/alumnos/'.$colegioB['alumno']->id.'/gestiones-cobranza')->assertNotFound();
});

it('no genera consultas N+1 al aumentar los alumnos con deuda', function (): void {
    $escenario = prepararEscenarioB6('deudas-consultas');
    $this->actingAs($escenario['usuario']);
    $consultas = [];
    DB::listen(function (QueryExecuted $consulta) use (&$consultas): void {
        if (str_starts_with(mb_strtolower(ltrim($consulta->sql)), 'select')) {
            $consultas[] = $consulta->sql;
        }
    });

    $antesPrimeraConsulta = count($consultas);
    $this->getJson('/api/v1/deudas?per_page=100')->assertOk();
    $consultasUnAlumno = array_slice($consultas, $antesPrimeraConsulta);
    for ($indice = 1; $indice < 8; $indice++) {
        agregarAlumnoConDeudaB6($escenario, $indice);
    }
    $antesSegundaConsulta = count($consultas);
    $this->getJson('/api/v1/deudas?per_page=100')->assertOk();
    $consultasOchoAlumnos = array_slice($consultas, $antesSegundaConsulta);

    $tablasConsultadas = fn (array $consultas): array => collect($consultas)
        ->map(function (string $sql): string {
            preg_match('/\b(?:from|join)\s+`?([a-z_]+)/i', $sql, $coincidencias);

            return $coincidencias[1] ?? 'other';
        })
        ->reject(fn (string $tabla): bool => in_array($tabla, ['permissions', 'roles'], true))
        ->values()
        ->all();
    expect($tablasConsultadas($consultasOchoAlumnos))->toEqual($tablasConsultadas($consultasUnAlumno));
});

it('responde los listados y reportes grandes en menos de cinco segundos con quinientos alumnos', function (): void {
    $escenario = prepararEscenarioB6('deudas-rendimiento');
    $alumnos = [];
    $documentos = [];
    $ahora = now();

    for ($indice = 1; $indice < 500; $indice++) {
        $documento = 'B6'.$escenario['colegio']->id.str_pad((string) $indice, 8, '0', STR_PAD_LEFT);
        $documentos[] = $documento;
        $alumnos[] = [
            'colegio_id' => $escenario['colegio']->id,
            'nombre' => 'Alumno',
            'apellido' => 'Rendimiento '.$indice,
            'tipo_documento' => 'DNI',
            'documento_identidad' => $documento,
            'fecha_nacimiento' => '2015-03-15',
            'sexo' => 'M',
            'estado' => 'activo',
            'activo' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }
    foreach (array_chunk($alumnos, 100) as $lote) {
        DB::table('alumnos')->insert($lote);
    }
    $alumnoIds = DB::table('alumnos')
        ->where('colegio_id', $escenario['colegio']->id)
        ->whereIn('documento_identidad', $documentos)
        ->pluck('id');
    $matriculas = [];
    foreach ($alumnoIds as $alumnoId) {
        $matriculas[] = [
            'colegio_id' => $escenario['colegio']->id,
            'alumno_id' => $alumnoId,
            'anio_escolar_id' => $escenario['anio']->id,
            'grado_id' => $escenario['grado']->id,
            'seccion_id' => $escenario['seccion']->id,
            'usuario_id' => $escenario['usuario']->id,
            'anio_escolar' => '2026',
            'grado' => '1ro',
            'seccion' => 'A',
            'estado' => 'matriculado',
            'fecha_matricula' => today()->toDateString(),
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }
    foreach (array_chunk($matriculas, 100) as $lote) {
        DB::table('matriculas')->insert($lote);
    }
    $matriculaIds = DB::table('matriculas')
        ->where('colegio_id', $escenario['colegio']->id)
        ->whereIn('alumno_id', $alumnoIds)
        ->pluck('id', 'alumno_id');
    $cuotas = [];
    foreach ($matriculaIds as $alumnoId => $matriculaId) {
        $cuotas[] = [
            'colegio_id' => $escenario['colegio']->id,
            'matricula_id' => $matriculaId,
            'alumno_id' => $alumnoId,
            'anio_escolar_id' => $escenario['anio']->id,
            'concepto' => 'pension',
            'numero' => 1,
            'descripcion' => 'Cuota de rendimiento ficticia',
            'monto_original' => '10.00',
            'vencimiento' => today()->subDays(2)->toDateString(),
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }
    foreach (array_chunk($cuotas, 100) as $lote) {
        DB::table('cuotas')->insert($lote);
    }
    $this->actingAs($escenario['usuario']);

    foreach ([
        '/api/v1/deudas?per_page=100',
        '/api/v1/deudas/por-aula',
        '/api/v1/reportes/matriculas',
        '/api/v1/reportes/ingresos-mensuales',
        '/api/v1/reportes/dashboard',
    ] as $ruta) {
        $inicio = microtime(true);
        $this->getJson($ruta)->assertOk();
        $duracionMilisegundos = (microtime(true) - $inicio) * 1000;

        expect($duracionMilisegundos)->toBeLessThan(5000);
    }

    $this->getJson('/api/v1/deudas?per_page=100')
        ->assertOk()
        ->assertJsonPath('data.paginacion.total', 500);
});
