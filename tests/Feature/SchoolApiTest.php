<?php

use App\Models\Alumno;
use App\Models\Colegio;
use App\Models\Usuario;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function crearColegioPrueba(string $subdominio): Colegio
{
    return Colegio::query()->create([
        'nombre' => 'Colegio '.$subdominio,
        'subdominio' => $subdominio,
        'colores' => ['primario' => '#123456'],
        'activo' => true,
    ]);
}

function crearUsuarioPrueba(?Colegio $colegio, string $email, string $rol, array $permisos = []): Usuario
{
    $usuario = Usuario::query()->create([
        'nombre' => 'Usuario',
        'apellido' => 'Prueba',
        'email' => $email,
        'password' => Hash::make('Password123!'),
        'activo' => true,
    ]);
    $usuario->forceFill(['colegio_id' => $colegio?->id])->save();

    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    setPermissionsTeamId($colegio?->id);
    $role = Role::findOrCreate($rol, 'web');
    $role->syncPermissions($permisos);
    $usuario->assignRole($role);
    setPermissionsTeamId(null);

    return $usuario;
}

function crearAlumnoPrueba(Colegio $colegio, string $nombre): Alumno
{
    return Alumno::query()->create([
        'colegio_id' => $colegio->id,
        'nombre' => $nombre,
        'apellido' => 'Ficticio',
        'estado' => 'activo',
    ]);
}

test('el login SPA crea una sesión y no devuelve un token móvil', function () {
    $colegio = crearColegioPrueba('salesian-innova');
    $usuario = crearUsuarioPrueba($colegio, 'director@example.test', 'Director');

    $response = $this->withHeader('Origin', 'http://localhost:5173')
        ->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'Password123!',
            'subdominio' => $colegio->subdominio,
        ]);

    $response->assertOk()
        ->assertJsonPath('code', 'OK')
        ->assertJsonPath('data.token', null);
    $this->assertAuthenticatedAs($usuario, 'web');
});

test('el endpoint móvil emite un token Sanctum con vencimiento y el token autentica al usuario', function () {
    $colegio = crearColegioPrueba('salesian-innova');
    $usuario = crearUsuarioPrueba($colegio, 'docente@example.test', 'Docente');

    $tokenResponse = $this->postJson('/api/v1/auth/token', [
        'email' => $usuario->email,
        'password' => 'Password123!',
        'subdominio' => $colegio->subdominio,
    ]);

    $tokenResponse->assertOk();
    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $usuario->id,
        'name' => 'movil',
    ]);

    $this->withToken($tokenResponse->json('data.token'))
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.usuario.email', $usuario->email);
});

test('el cierre de sesión móvil revoca el token actual', function () {
    $colegio = crearColegioPrueba('salesian-innova');
    $usuario = crearUsuarioPrueba($colegio, 'docente@example.test', 'Docente');
    $token = $usuario->createToken('movil', ['*'], now()->addDays(30));

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('code', 'OK');

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
});

test('el quinto intento fallido bloquea temporalmente la cuenta', function () {
    $colegio = crearColegioPrueba('salesian-innova');
    $usuario = crearUsuarioPrueba($colegio, 'director@example.test', 'Director');

    for ($intento = 1; $intento < 5; $intento++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'incorrecta',
            'subdominio' => $colegio->subdominio,
        ])->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => $usuario->email,
        'password' => 'incorrecta',
        'subdominio' => $colegio->subdominio,
    ])->assertStatus(423)
        ->assertJsonPath('code', 'ACCOUNT_LOCKED');

    expect($usuario->fresh()->intentos_fallidos)->toBe(5)
        ->and($usuario->fresh()->bloqueado_hasta)->not->toBeNull();
});

test('el login rechaza una cuenta enviada desde el subdominio de otro colegio', function () {
    $colegioA = crearColegioPrueba('colegio-a');
    crearColegioPrueba('colegio-b');
    $usuarioA = crearUsuarioPrueba($colegioA, 'director-a@example.test', 'Director');

    $this->withHeader('Origin', 'https://colegio-b.example.test')
        ->postJson('/api/v1/auth/login', [
            'email' => $usuarioA->email,
            'password' => 'Password123!',
            'subdominio' => 'colegio-a',
        ])->assertUnauthorized()
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');

    $this->assertGuest('web');
});

test('la recuperación de contraseña no revela si el correo está registrado', function () {
    $this->postJson('/api/v1/auth/recuperar-contrasena', ['email' => 'ausente@example.test'])
        ->assertOk()
        ->assertJsonPath('code', 'OK')
        ->assertJsonPath(
            'message',
            'Si el correo está registrado, recibirás instrucciones para recuperar tu contraseña.',
        );
});

test('el usuario puede restablecer su contraseña con el token enviado por correo', function () {
    Notification::fake();

    $colegio = crearColegioPrueba('salesian-innova');
    $usuario = crearUsuarioPrueba($colegio, 'director@example.test', 'Director');

    $this->postJson('/api/v1/auth/recuperar-contrasena', ['email' => $usuario->email])
        ->assertOk();

    $token = null;
    Notification::assertSentTo(
        $usuario,
        ResetPassword::class,
        function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        },
    );

    $this->postJson('/api/v1/auth/restablecer-contrasena', [
        'email' => $usuario->email,
        'token' => $token,
        'password' => 'NuevaPassword123!',
        'password_confirmation' => 'NuevaPassword123!',
    ])->assertOk()
        ->assertJsonPath('code', 'OK');

    expect(Hash::check('NuevaPassword123!', $usuario->fresh()->password))->toBeTrue();
});

test('los errores de validación usan el contrato JSON común en español', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('message', 'Los datos enviados no son válidos.')
        ->assertJsonPath('errors.email.0', 'El campo correo electrónico es obligatorio.');
});

test('los permisos son propios del rol y del colegio', function () {
    $colegioA = crearColegioPrueba('colegio-a');
    $colegioB = crearColegioPrueba('colegio-b');
    $usuarioA = crearUsuarioPrueba($colegioA, 'director-a@example.test', 'Director', ['alumnos.ver']);
    $alumnoB = crearAlumnoPrueba($colegioB, 'Alumno B');

    $this->actingAs($usuarioA, 'sanctum')
        ->getJson('/api/v1/alumnos/'.$alumnoB->id)
        ->assertNotFound();

    $usuarioSinPermiso = crearUsuarioPrueba(
        $colegioA,
        'docente-a@example.test',
        'Docente',
        ['avance.registrar'],
    );

    $this->actingAs($usuarioSinPermiso, 'sanctum')
        ->getJson('/api/v1/alumnos/'.$alumnoB->id)
        ->assertForbidden();
});

test('un usuario del colegio A no puede leer ni modificar alumnos del colegio B', function () {
    $colegioA = crearColegioPrueba('colegio-a');
    $colegioB = crearColegioPrueba('colegio-b');
    $alumnoB = crearAlumnoPrueba($colegioB, 'Alumno B');
    $usuarioA = crearUsuarioPrueba(
        $colegioA,
        'director-a@example.test',
        'Director',
        ['alumnos.ver', 'alumnos.editar'],
    );

    $this->actingAs($usuarioA, 'sanctum')
        ->getJson('/api/v1/alumnos/'.$alumnoB->id)
        ->assertNotFound();

    $this->patchJson('/api/v1/alumnos/'.$alumnoB->id, ['nombre' => 'Alterado'])
        ->assertNotFound();

    expect(Alumno::withoutGlobalScope('colegio')->findOrFail($alumnoB->id)->nombre)
        ->toBe('Alumno B');
});

test('el colegio se asigna desde el usuario autenticado al crear un registro', function () {
    $colegioA = crearColegioPrueba('colegio-a');
    $colegioB = crearColegioPrueba('colegio-b');
    $usuarioA = crearUsuarioPrueba($colegioA, 'director-a@example.test', 'Director');

    $this->actingAs($usuarioA, 'sanctum');

    $alumno = Alumno::query()->create([
        'colegio_id' => $colegioB->id,
        'nombre' => 'Alumno A',
        'apellido' => 'Ficticio',
        'estado' => 'activo',
    ]);

    expect($alumno->colegio_id)->toBe($colegioA->id);
});

test('el endpoint público solo expone identidad visual y responde con el formato común', function () {
    $colegio = crearColegioPrueba('salesian-innova');

    $this->getJson('/api/v1/publico/colegio?subdominio='.$colegio->subdominio)
        ->assertOk()
        ->assertJsonPath('code', 'OK')
        ->assertJsonPath('data.nombre', $colegio->nombre)
        ->assertJsonPath('data.colores.primario', '#123456')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.subdominio');
});
