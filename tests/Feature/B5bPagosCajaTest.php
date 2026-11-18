<?php

use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Auditoria;
use App\Models\ClaveIdempotencia;
use App\Models\Colegio;
use App\Models\ConfiguracionColegio;
use App\Models\Cuota;
use App\Models\Grado;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Pago;
use App\Models\Seccion;
use App\Models\Usuario;
use App\Services\EstadoDeCuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-14 12:00:00');
});

/**
 * @return array{colegio: Colegio, usuario: Usuario, matricula: Matricula, alumno: Alumno, cuotas: list<Cuota>}
 */
function prepararEscenarioPagosB5b(
    string $subdominio,
    string $rol = 'Director',
    int $cantidadCuotas = 2,
    string $montoCuota = '100.00',
    ?array $permisos = null,
): array {
    $colegio = Colegio::query()->create([
        'nombre' => 'Colegio '.$subdominio,
        'subdominio' => $subdominio,
        'activo' => true,
    ]);
    $permisos ??= match ($rol) {
        'Director' => ['pagos.ver', 'pagos.registrar', 'pagos.anular', 'caja.cerrar'],
        'Tesorería' => ['pagos.ver', 'pagos.registrar', 'pagos.anular', 'caja.cerrar'],
        'Administrador' => ['pagos.ver'],
        default => ['pagos.ver', 'pagos.registrar'],
    };
    $usuario = Usuario::query()->create([
        'nombre' => $rol,
        'apellido' => 'Prueba',
        'email' => $subdominio.'@prueba.test',
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

    $anio = AnioEscolar::query()->create([
        'colegio_id' => $colegio->id,
        'nombre' => (string) today()->year,
        'fecha_inicio' => today()->startOfYear()->toDateString(),
        'fecha_fin' => today()->endOfYear()->toDateString(),
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
        'numero_cuotas_pension' => max($cantidadCuotas, 1),
        'dia_vencimiento' => 10,
        'serie_recibos' => 'R001',
        'frecuencia_registro_avance' => 'semanal',
        'recargo_mora_activo' => false,
    ]);
    $alumno = Alumno::query()->create([
        'colegio_id' => $colegio->id,
        'nombre' => 'Alumno',
        'apellido' => 'Prueba',
        'tipo_documento' => 'DNI',
        'documento_identidad' => 'B5B'.$colegio->id,
        'fecha_nacimiento' => '2015-03-15',
        'sexo' => 'F',
        'direccion' => 'Dirección ficticia',
        'estado' => 'activo',
        'activo' => true,
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
        'estado' => 'matriculado',
        'fecha_matricula' => today()->toDateString(),
    ]);
    $cuotas = [];

    for ($numero = 1; $numero <= $cantidadCuotas; $numero++) {
        $cuotas[] = Cuota::query()->create([
            'colegio_id' => $colegio->id,
            'matricula_id' => $matricula->id,
            'alumno_id' => $alumno->id,
            'anio_escolar_id' => $anio->id,
            'concepto' => 'pension',
            'numero' => $numero,
            'descripcion' => 'Pensión de prueba '.$numero,
            'monto_original' => $montoCuota,
            'vencimiento' => today()->subDays($cantidadCuotas - $numero + 1)->toDateString(),
        ]);
    }

    return compact('colegio', 'usuario', 'matricula', 'alumno', 'cuotas');
}

/**
 * @param  array<string, mixed>  $sobrescrituras
 * @return array<string, mixed>
 */
function datosPagoB5b(Matricula $matricula, string $monto = '100.00', array $sobrescrituras = []): array
{
    return [
        'matricula_id' => $matricula->id,
        'fecha_pago' => today()->toDateString(),
        'monto' => $monto,
        'medio' => 'efectivo',
        ...$sobrescrituras,
    ];
}

function registrarPagoHttpB5b(
    TestCase $prueba,
    array $datos,
    string $clave = 'pago-b5b-1',
): TestResponse {
    return $prueba->postJson('/api/v1/pagos', $datos, ['Idempotency-Key' => $clave]);
}

it('registra el pago, aplica la cuota, numera y permite reimprimir la constancia', function (): void {
    $escenario = prepararEscenarioPagosB5b('pago-completo');
    $this->actingAs($escenario['usuario']);

    $respuesta = registrarPagoHttpB5b($this, datosPagoB5b(
        $escenario['matricula'],
        '100.00',
        ['aplicaciones' => [['cuota_id' => $escenario['cuotas'][0]->id, 'monto' => '100.00']]],
    ));

    $respuesta->assertCreated()
        ->assertJsonPath('data.pago.constancia', 'R001-000001')
        ->assertJsonPath('data.saldos.0.saldo', '0.00')
        ->assertJsonPath('data.saldos.0.estado', 'pagada');
    $this->assertDatabaseHas('pagos', ['colegio_id' => $escenario['colegio']->id, 'monto' => '100.00']);
    $this->get('/api/v1/pagos/'.$respuesta->json('data.pago.id').'/constancia')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('responde 401 cuando una visita consulta los pagos', function (): void {
    $this->getJson('/api/v1/pagos')->assertUnauthorized();
});

it('devuelve 422 para fecha futura y operación omitida en una transferencia', function (): void {
    $escenario = prepararEscenarioPagosB5b('validacion-pago');
    $this->actingAs($escenario['usuario']);

    registrarPagoHttpB5b($this, datosPagoB5b($escenario['matricula'], '100.00', [
        'fecha_pago' => today()->addDay()->toDateString(),
    ]), 'fecha-futura')->assertUnprocessable()->assertJsonValidationErrors('fecha_pago');
    registrarPagoHttpB5b($this, datosPagoB5b($escenario['matricula'], '100.00', [
        'medio' => 'transferencia',
    ]), 'sin-operacion')->assertUnprocessable()->assertJsonValidationErrors('numero_operacion');
});

it('aplica automáticamente por vencimiento y admite pagos parciales y de varias cuotas', function (): void {
    $escenario = prepararEscenarioPagosB5b('pago-multiple');
    $this->actingAs($escenario['usuario']);

    $respuesta = registrarPagoHttpB5b($this, datosPagoB5b($escenario['matricula'], '150.00'));

    $respuesta->assertCreated()
        ->assertJsonPath('data.saldos.0.estado', 'pagada')
        ->assertJsonPath('data.saldos.1.saldo', '50.00')
        ->assertJsonPath('data.saldos.1.estado', 'parcial');
    expect($respuesta->json('data.pago.aplicaciones'))->toHaveCount(2);
    expect($respuesta->json('data.pago.aplicaciones.0.cuota_id'))->toBe($escenario['cuotas'][0]->id);
});

it('devuelve 422 por sobrepago y 409 por clave reutilizada con otro cuerpo sin saltar números', function (): void {
    $escenario = prepararEscenarioPagosB5b('idempotencia');
    $this->actingAs($escenario['usuario']);
    $datos = datosPagoB5b($escenario['matricula'], '250.00');

    registrarPagoHttpB5b($this, $datos, 'clave-reutilizable')->assertUnprocessable();
    $this->assertDatabaseCount('pagos', 0);

    $datos['monto'] = '100.00';
    $primera = registrarPagoHttpB5b($this, $datos, 'clave-reutilizable')->assertCreated();
    $reintento = registrarPagoHttpB5b($this, $datos, 'clave-reutilizable')->assertCreated();
    expect($reintento->json())->toBe($primera->json());
    $this->assertDatabaseCount('pagos', 1);

    $datos['monto'] = '50.00';
    registrarPagoHttpB5b($this, $datos, 'clave-reutilizable')
        ->assertStatus(409)
        ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

    registrarPagoHttpB5b($this, datosPagoB5b($escenario['matricula'], '100.00'), 'otra-clave')
        ->assertJsonPath('data.pago.constancia', 'R001-000002');
    $this->postJson('/api/v1/pagos', datosPagoB5b($escenario['matricula']), [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $datosConColegio = datosPagoB5b($escenario['matricula'], '10.00', ['colegio_id' => $escenario['colegio']->id]);
    registrarPagoHttpB5b($this, $datosConColegio, 'colegio-no-permitido')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('colegio_id');
});

it('permite reutilizar la clave de idempotencia después de 24 horas', function (): void {
    $escenario = prepararEscenarioPagosB5b('expiracion-idempotencia');
    $this->actingAs($escenario['usuario']);
    $datos = datosPagoB5b($escenario['matricula']);

    registrarPagoHttpB5b($this, $datos, 'clave-expirada')->assertCreated();
    ClaveIdempotencia::query()->where('clave', 'clave-expirada')->update(['vence_en' => now()->subSecond()]);

    registrarPagoHttpB5b($this, datosPagoB5b($escenario['matricula'], '50.00'), 'clave-expirada')
        ->assertCreated()
        ->assertJsonPath('data.pago.constancia', 'R001-000002');
    $this->assertDatabaseCount('pagos', 2);
});

it('devuelve 422 cuando una aplicación manual supera el saldo de la cuota', function (): void {
    $escenario = prepararEscenarioPagosB5b('aplicacion-manual');
    $this->actingAs($escenario['usuario']);
    $datos = datosPagoB5b($escenario['matricula'], '150.00', [
        'aplicaciones' => [
            ['cuota_id' => $escenario['cuotas'][0]->id, 'monto' => '50.00'],
            ['cuota_id' => $escenario['cuotas'][1]->id, 'monto' => '100.00'],
        ],
    ]);

    registrarPagoHttpB5b($this, $datos)->assertCreated();

    $datos['monto'] = '100.00';
    $datos['aplicaciones'] = [['cuota_id' => $escenario['cuotas'][0]->id, 'monto' => '100.00']];
    registrarPagoHttpB5b($this, $datos, 'exceso-cuota')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');
    $datos['monto'] = '100.00';
    $datos['aplicaciones'][0]['monto'] = '90.00';
    registrarPagoHttpB5b($this, $datos, 'suma-incorrecta')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('aplicaciones');
});

it('anula el pago con motivo, revierte el saldo y no audita el numero de operacion', function (): void {
    $escenario = prepararEscenarioPagosB5b('anulacion');
    $this->actingAs($escenario['usuario']);
    $respuesta = registrarPagoHttpB5b($this, datosPagoB5b(
        $escenario['matricula'],
        '100.00',
        ['medio' => 'transferencia', 'numero_operacion' => 'OP-987654321'],
    ))->assertCreated();
    $pagoId = $respuesta->json('data.pago.id');

    $this->postJson('/api/v1/pagos/'.$pagoId.'/anular', ['motivo' => 'Operación duplicada'])
        ->assertOk()
        ->assertJsonPath('data.pago.estado', 'anulado')
        ->assertJsonPath('data.saldos.0.saldo', '100.00')
        ->assertJsonPath('data.saldos.0.estado', 'vencida');

    $estado = app(EstadoDeCuota::class)->calcular($escenario['cuotas'][0]->fresh());
    expect($estado['saldo'])->toBe('100.00');
    expect($estado['estado'])->toBe('vencida');
    $auditoria = Auditoria::query()->where('entidad', 'pagos')->get()->toJson();
    expect($auditoria)->not->toContain('OP-987654321');

    $this->get('/api/v1/pagos/'.$pagoId.'/constancia')
        ->assertDownload('constancia-R001-000001.pdf');
});

it('expone el estado de cuenta en JSON y PDF con totales derivados', function (): void {
    $escenario = prepararEscenarioPagosB5b('estado-cuenta');
    $this->actingAs($escenario['usuario']);
    registrarPagoHttpB5b($this, datosPagoB5b($escenario['matricula']))->assertCreated();

    $this->getJson('/api/v1/alumnos/'.$escenario['alumno']->id.'/estado-cuenta')
        ->assertOk()
        ->assertJsonPath('data.totales.emitido', '200.00')
        ->assertJsonPath('data.totales.pagado', '100.00')
        ->assertJsonPath('data.totales.saldo', '100.00');
    $this->get('/api/v1/alumnos/'.$escenario['alumno']->id.'/estado-cuenta/pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('devuelve 422 para vouchers inválidos y permite descargar el archivo privado', function (): void {
    $escenario = prepararEscenarioPagosB5b('voucher-caja');
    Storage::fake('local');
    $this->actingAs($escenario['usuario']);
    $respuesta = registrarPagoHttpB5b($this, datosPagoB5b(
        $escenario['matricula'],
        '100.00',
        ['medio' => 'deposito', 'numero_operacion' => 'DEP-12345'],
    ))->assertCreated();
    $pagoId = $respuesta->json('data.pago.id');
    $archivo = UploadedFile::fake()->createWithContent(
        'voucher.pdf',
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
    );

    $this->post('/api/v1/pagos/'.$pagoId.'/voucher', ['voucher' => $archivo])
        ->assertOk()
        ->assertJsonPath('data.pago.voucher_disponible', true);
    $this->post('/api/v1/pagos/'.$pagoId.'/voucher', [
        'voucher' => UploadedFile::fake()->createWithContent('voucher.txt', 'contenido no valido'),
    ])->assertUnprocessable();
    $this->post('/api/v1/pagos/'.$pagoId.'/voucher', [
        'voucher' => UploadedFile::fake()->create('voucher-grande.pdf', 2049, 'application/pdf'),
    ])->assertUnprocessable();
    $pago = Pago::query()->findOrFail($pagoId);
    Storage::disk('local')->assertExists($pago->voucher_path);
    $this->get('/api/v1/pagos/'.$pagoId.'/voucher')->assertDownload();

    $this->getJson('/api/v1/caja/resumen?fecha='.today()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.total', '100.00')
        ->assertJsonPath('data.por_usuario.0.total', '100.00')
        ->assertJsonPath('data.por_medio.0.medio', 'deposito');
    $this->postJson('/api/v1/caja/cierres', [
        'fecha' => today()->toDateString(),
        'observacion' => 'Cierre de prueba',
    ])->assertCreated();
    $this->postJson('/api/v1/caja/cierres', ['fecha' => today()->toDateString()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fecha');
});

it('limita a treinta escrituras de pago por minuto y devuelve el error común 429', function (): void {
    $escenario = prepararEscenarioPagosB5b('limite-pagos');
    $this->actingAs($escenario['usuario']);

    for ($intento = 1; $intento <= 30; $intento++) {
        $this->postJson('/api/v1/pagos', [], ['Idempotency-Key' => 'limite-'.$intento])
            ->assertUnprocessable();
    }

    $this->postJson('/api/v1/pagos', [], ['Idempotency-Key' => 'limite-31'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_REQUESTS');
});

it('devuelve 404 para pagos de otro colegio y 403 a roles docentes', function (): void {
    $colegioA = prepararEscenarioPagosB5b('aislamiento-a');
    $colegioB = prepararEscenarioPagosB5b('aislamiento-b');
    $this->actingAs($colegioB['usuario']);
    $pagoB = registrarPagoHttpB5b($this, datosPagoB5b($colegioB['matricula']), 'colegio-b')
        ->assertCreated()
        ->json('data.pago.id');

    $this->actingAs($colegioA['usuario']);
    $this->getJson('/api/v1/pagos')->assertOk()->assertJsonCount(0, 'data.pagos');
    $this->getJson('/api/v1/pagos/'.$pagoB)->assertNotFound();
    $this->postJson('/api/v1/pagos/'.$pagoB.'/anular', ['motivo' => 'Acceso cruzado'])
        ->assertNotFound();
    $this->postJson('/api/v1/pagos', datosPagoB5b($colegioB['matricula']), [
        'Idempotency-Key' => 'no-debe-registrar',
    ])->assertUnprocessable();

    $this->getJson('/api/v1/pagos?colegio_id='.$colegioA['colegio']->id)->assertUnprocessable();

    auth()->forgetGuards();
    $sinAcceso = prepararEscenarioPagosB5b(
        'docente-sin-acceso',
        'Docente',
        permisos: ['pagos.ver', 'pagos.registrar'],
    );
    $this->actingAs($sinAcceso['usuario']);
    $this->getJson('/api/v1/pagos')->assertForbidden();
    $this->postJson('/api/v1/pagos', datosPagoB5b($sinAcceso['matricula']), [
        'Idempotency-Key' => 'sin-permiso-docente',
    ])->assertForbidden();
});

it('allows Treasury to register, annul, and close cash while administrators remain read-only', function (): void {
    $tesoreria = prepararEscenarioPagosB5b('tesoreria-permisos', 'Tesorería');
    $this->actingAs($tesoreria['usuario']);
    $pago = registrarPagoHttpB5b($this, datosPagoB5b($tesoreria['matricula']), 'tesoreria-pago')
        ->assertCreated()
        ->json('data.pago.id');
    $this->postJson('/api/v1/pagos/'.$pago.'/anular', ['motivo' => 'Registro de prueba'])
        ->assertOk();
    $this->postJson('/api/v1/caja/cierres', ['fecha' => today()->toDateString()])
        ->assertCreated();

    auth()->forgetGuards();
    $administrador = prepararEscenarioPagosB5b('administrador-permisos', 'Administrador');
    $this->actingAs($administrador['usuario']);
    $this->getJson('/api/v1/pagos')->assertOk();
    $this->postJson('/api/v1/pagos', datosPagoB5b($administrador['matricula']), [
        'Idempotency-Key' => 'admin-no-registra',
    ])->assertForbidden();
    $this->postJson('/api/v1/caja/cierres', ['fecha' => today()->toDateString()])
        ->assertForbidden();
});
it('denies payment policy abilities to teaching, assistant, and parent roles', function (): void {
    foreach (['Docente', 'Auxiliar', 'Apoderado'] as $rol) {
        auth()->forgetGuards();
        $sinAcceso = prepararEscenarioPagosB5b(
            strtolower($rol).'-policy',
            $rol,
            permisos: ['pagos.ver', 'pagos.registrar', 'pagos.anular'],
        );
        setPermissionsTeamId($sinAcceso['colegio']->id);

        expect($sinAcceso['usuario']->can('viewAny', Pago::class))->toBeFalse();
        expect($sinAcceso['usuario']->can('create', Pago::class))->toBeFalse();

        setPermissionsTeamId(null);
    }
});
