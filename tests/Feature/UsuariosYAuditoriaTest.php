<?php

use App\Models\Auditoria;
use App\Models\Colegio;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function crearColegioB2(string $subdominio): Colegio
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
function crearUsuarioB2(Colegio $colegio, string $email, string $rol, array $permisos = []): Usuario
{
    $usuario = Usuario::query()->create([
        'nombre' => 'Usuario',
        'apellido' => 'Prueba',
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
 * @return list<string>
 */
function permisosGestionB2(): array
{
    return [
        'usuarios.ver',
        'usuarios.crear',
        'usuarios.editar',
        'usuarios.desactivar',
        'usuarios.reactivar',
        'roles.ver',
        'permisos.ver',
        'auditoria.ver',
    ];
}

function asegurarRolB2(Colegio $colegio, string $nombre): Role
{
    setPermissionsTeamId($colegio->id);
    $rol = Role::findOrCreate($nombre, 'web');
    setPermissionsTeamId(null);

    return $rol;
}

test('lista usuarios del colegio con busqueda filtros y paginacion sin exponer datos sensibles', function () {
    $colegio = crearColegioB2('colegio-a');
    $director = crearUsuarioB2($colegio, 'director@example.test', 'Director', permisosGestionB2());
    $docente = crearUsuarioB2($colegio, 'ana.docente@example.test', 'Docente');
    crearUsuarioB2($colegio, 'inactivo@example.test', 'Docente')->forceFill(['activo' => false])->save();
    crearUsuarioB2($colegio, 'tesoreria@example.test', 'Tesorería');

    $this->actingAs($director, 'sanctum')
        ->getJson('/api/v1/usuarios?buscar=ana&rol=Docente&estado=activo&per_page=1')
        ->assertOk()
        ->assertJsonPath('code', 'OK')
        ->assertJsonPath('data.usuarios.0.email', $docente->email)
        ->assertJsonPath('data.paginacion.por_pagina', 1)
        ->assertJsonMissingPath('data.usuarios.0.password')
        ->assertJsonMissingPath('data.usuarios.0.remember_token');
});

test('crea un usuario en el colegio del actor con rol y contraseña temporal de un solo uso', function () {
    $colegio = crearColegioB2('colegio-a');
    $director = crearUsuarioB2($colegio, 'admin@example.test', 'Administrador', permisosGestionB2());
    asegurarRolB2($colegio, 'Docente');

    $respuesta = $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'nombre' => 'Daniel',
            'apellido' => 'Docente',
            'email' => 'daniel@example.test',
            'rol' => 'Docente',
        ]);

    $respuesta->assertCreated()
        ->assertJsonPath('data.usuario.requiere_cambio_contrasena', true)
        ->assertJsonPath('data.usuario.roles.0', 'Docente')
        ->assertJsonMissingPath('data.usuario.password')
        ->assertJsonStructure(['data' => ['contrasena_temporal']]);

    $usuario = Usuario::query()->where('email', 'daniel@example.test')->firstOrFail();
    expect($usuario->colegio_id)->toBe($colegio->id)
        ->and($usuario->activo)->toBeTrue()
        ->and($usuario->requiere_cambio_contrasena)->toBeTrue()
        ->and(Hash::check($respuesta->json('data.contrasena_temporal'), $usuario->password))->toBeTrue()
        ->and(strlen($respuesta->json('data.contrasena_temporal')))->toBeGreaterThanOrEqual(10);
});

test('rechaza colegio_id enviado por el cliente al crear un usuario', function () {
    $colegio = crearColegioB2('colegio-a');
    $director = crearUsuarioB2($colegio, 'director@example.test', 'Director', permisosGestionB2());
    asegurarRolB2($colegio, 'Docente');

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'nombre' => 'Daniel',
            'apellido' => 'Docente',
            'email' => 'daniel@example.test',
            'rol' => 'Docente',
            'colegio_id' => 999,
        ])->assertUnprocessable();

    $this->assertDatabaseMissing('usuarios', ['email' => 'daniel@example.test']);
});

test('impide asignar un rol superior o crear un superadministrador desde un colegio', function () {
    $colegio = crearColegioB2('colegio-a');
    $administrador = crearUsuarioB2($colegio, 'admin@example.test', 'Administrador', permisosGestionB2());
    asegurarRolB2($colegio, 'Director');

    $this->actingAs($administrador, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'nombre' => 'Director',
            'apellido' => 'Nuevo',
            'email' => 'nuevo-director@example.test',
            'rol' => 'Director',
        ])->assertForbidden();

    $this->postJson('/api/v1/usuarios', [
        'nombre' => 'Super',
        'apellido' => 'Admin',
        'email' => 'nuevo-superadmin@example.test',
        'rol' => 'Superadministrador',
    ])->assertUnprocessable();

    $this->assertDatabaseMissing('usuarios', ['email' => 'nuevo-director@example.test']);
    $this->assertDatabaseMissing('usuarios', ['email' => 'nuevo-superadmin@example.test']);
});

test('solo Director y Administrador pueden gestionar usuarios', function () {
    $colegio = crearColegioB2('colegio-a');
    $docente = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');

    $this->actingAs($docente, 'sanctum')
        ->getJson('/api/v1/usuarios')
        ->assertForbidden();

    $this->getJson('/api/v1/roles')->assertForbidden();
    $this->getJson('/api/v1/auditoria')->assertForbidden();

    $this->postJson('/api/v1/usuarios', [
        'nombre' => 'Otro',
        'apellido' => 'Docente',
        'email' => 'otro@example.test',
        'rol' => 'Docente',
    ])->assertForbidden();
});

test('requiere autenticacion para las rutas de usuarios y auditoria', function () {
    $this->getJson('/api/v1/usuarios')->assertUnauthorized();
    $this->getJson('/api/v1/auditoria')->assertUnauthorized();
});

test('impide auto desactivacion y proteger el ultimo Administrador activo', function () {
    $colegio = crearColegioB2('colegio-a');
    $director = crearUsuarioB2($colegio, 'director@example.test', 'Director', permisosGestionB2());
    $administrador = crearUsuarioB2($colegio, 'admin@example.test', 'Administrador');

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/usuarios/'.$director->id.'/desactivar')
        ->assertUnprocessable();

    $this->postJson('/api/v1/usuarios/'.$administrador->id.'/desactivar')
        ->assertUnprocessable();

    expect($administrador->fresh()->activo)->toBeTrue();
});

test('impide quitar el rol del ultimo Administrador activo', function () {
    $colegio = crearColegioB2('colegio-a');
    $director = crearUsuarioB2($colegio, 'director@example.test', 'Director', permisosGestionB2());
    $administrador = crearUsuarioB2($colegio, 'admin@example.test', 'Administrador');
    asegurarRolB2($colegio, 'Docente');

    $this->actingAs($director, 'sanctum')
        ->patchJson('/api/v1/usuarios/'.$administrador->id, ['rol' => 'Docente'])
        ->assertUnprocessable();

    setPermissionsTeamId($colegio->id);
    expect($administrador->fresh()->getRoleNames()->all())->toBe(['Administrador']);
    setPermissionsTeamId(null);
});

test('desactiva y reactiva usuarios sin eliminarlos', function () {
    $colegio = crearColegioB2('colegio-a');
    $director = crearUsuarioB2($colegio, 'director@example.test', 'Director', permisosGestionB2());
    $usuario = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');
    $token = $usuario->createToken('movil');

    $this->actingAs($director, 'sanctum')
        ->getJson('/api/v1/usuarios/'.$usuario->id)
        ->assertOk()
        ->assertJsonPath('data.usuario.email', $usuario->email);

    $this->postJson('/api/v1/usuarios/'.$usuario->id.'/desactivar')
        ->assertOk()
        ->assertJsonPath('data.usuario.activo', false);

    $this->postJson('/api/v1/usuarios/'.$usuario->id.'/reactivar')
        ->assertOk()
        ->assertJsonPath('data.usuario.activo', true);

    $this->deleteJson('/api/v1/usuarios/'.$usuario->id)->assertMethodNotAllowed();

    $this->assertModelExists($usuario->fresh());
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
});

test('desactivar y reactivar quedan auditados sin eliminar el usuario', function () {
    $colegio = crearColegioB2('colegio-a');
    $director = crearUsuarioB2($colegio, 'director@example.test', 'Director', permisosGestionB2());
    $usuario = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/usuarios/'.$usuario->id.'/desactivar')
        ->assertOk()
        ->assertJsonPath('data.usuario.activo', false);

    $this->postJson('/api/v1/usuarios/'.$usuario->id.'/reactivar')
        ->assertOk()
        ->assertJsonPath('data.usuario.activo', true);

    expect(Auditoria::query()->where('entidad_id', $usuario->id)->pluck('evento')->all())
        ->toContain('usuarios.desactivado', 'usuarios.reactivado');
});

test('login informa el cambio obligatorio y restringe la sesion hasta actualizar la contrasena', function () {
    $colegio = crearColegioB2('colegio-a');
    $usuario = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');
    $usuario->forceFill(['requiere_cambio_contrasena' => true])->save();

    $this->withHeader('Origin', 'http://localhost:5173')
        ->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'Password123!',
            'subdominio' => $colegio->subdominio,
        ])->assertOk()
        ->assertJsonPath('data.usuario.requiere_cambio_contrasena', true);

    $this->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

    $this->postJson('/api/v1/auth/cambiar-contrasena', [
        'contrasena_actual' => 'Password123!',
        'contrasena' => 'NuevaPassword456!',
        'contrasena_confirmation' => 'NuevaPassword456!',
    ])->assertOk()
        ->assertJsonPath('data.usuario.requiere_cambio_contrasena', false);

    expect(Hash::check('NuevaPassword456!', $usuario->fresh()->password))->toBeTrue();

    $this->getJson('/api/v1/auth/me')->assertOk();
});

test('rechaza cambio de contrasena con clave actual incorrecta o menos de diez caracteres', function () {
    $colegio = crearColegioB2('colegio-a');
    $usuario = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/auth/cambiar-contrasena', [
            'contrasena_actual' => 'incorrecta',
            'contrasena' => 'corta',
            'contrasena_confirmation' => 'corta',
        ])->assertUnprocessable();

    expect(Hash::check('Password123!', $usuario->fresh()->password))->toBeTrue();

    $this->postJson('/api/v1/auth/cambiar-contrasena', [
        'contrasena_actual' => 'Password123!',
        'contrasena' => 'corta',
        'contrasena_confirmation' => 'corta',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.contrasena.0', 'La nueva contraseña debe tener al menos 10 caracteres.');
});

test('cierra una sesion SPA luego de treinta minutos sin actividad configurable', function () {
    config(['session.lifetime' => 30]);
    $colegio = crearColegioB2('colegio-a');
    $usuario = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');

    $this->withHeader('Origin', 'http://localhost:5173')
        ->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'Password123!',
            'subdominio' => $colegio->subdominio,
        ])->assertOk();

    $this->travel(31)->minutes();

    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'SESSION_EXPIRED');
});

test('permite cerrar sesion cuando el cambio de contrasena es obligatorio', function () {
    config(['session.lifetime' => 30]);
    $colegio = crearColegioB2('colegio-a');
    $usuario = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');
    $usuario->forceFill(['requiere_cambio_contrasena' => true])->save();

    $this->withHeader('Origin', 'http://localhost:5173')
        ->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'Password123!',
            'subdominio' => $colegio->subdominio,
        ])->assertOk();

    $this->postJson('/api/v1/auth/logout')->assertOk();
});

test('bloquea las sesiones existentes de una cuenta desactivada', function () {
    $colegio = crearColegioB2('colegio-a');
    $usuario = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');
    $usuario->forceFill(['activo' => false])->save();

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'USER_INACTIVE');
});

test('no aplica el vencimiento por inactividad de la SPA a los tokens moviles', function () {
    config(['session.lifetime' => 30]);
    $colegio = crearColegioB2('colegio-a');
    $usuarioMovil = crearUsuarioB2($colegio, 'movil@example.test', 'Docente');
    $token = $usuarioMovil->createToken('movil', ['*'], now()->addDays(30))->plainTextToken;
    $this->travel(31)->minutes();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
});

test('lista el catalogo de roles y permisos asignados al colegio', function () {
    $colegio = crearColegioB2('colegio-a');
    $director = crearUsuarioB2($colegio, 'director@example.test', 'Director', permisosGestionB2());

    $this->actingAs($director, 'sanctum')
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonFragment(['nombre' => 'Director']);

    $this->getJson('/api/v1/permisos')
        ->assertOk()
        ->assertJsonFragment(['nombre' => 'usuarios.crear']);
});

test('registra inicios de sesion exitosos sin almacenar la contrasena', function () {
    $colegio = crearColegioB2('colegio-a');
    $usuario = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');

    $this->withHeader('Origin', 'http://localhost:5173')
        ->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'Password123!',
            'subdominio' => $colegio->subdominio,
        ])->assertOk();

    $auditoria = Auditoria::query()->where('evento', 'auth.login_exitoso')->firstOrFail();
    expect($auditoria->usuario_id)->toBe($usuario->id)
        ->and($auditoria->valores_nuevos)->toBe(['metodo' => 'sesion'])
        ->and($auditoria->toJson())->not->toContain('Password123!');
});

test('audita creacion y cambio de rol sin incluir contrasenas ni secretos', function () {
    $colegio = crearColegioB2('colegio-a');
    $director = crearUsuarioB2($colegio, 'director@example.test', 'Director', permisosGestionB2());
    asegurarRolB2($colegio, 'Docente');
    asegurarRolB2($colegio, 'Auxiliar');

    $respuesta = $this->actingAs($director, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'nombre' => 'Daniel',
            'apellido' => 'Docente',
            'email' => 'daniel@example.test',
            'rol' => 'Docente',
        ])->assertCreated();

    $idUsuario = $respuesta->json('data.usuario.id');
    $this->patchJson('/api/v1/usuarios/'.$idUsuario, [
        'nombre' => 'Daniel Actualizado',
        'rol' => 'Auxiliar',
    ])->assertOk()
        ->assertJsonPath('data.usuario.nombre', 'Daniel Actualizado');

    $registros = Auditoria::query()->where('entidad_id', $idUsuario)->get();
    expect($registros->pluck('evento')->all())->toContain('usuarios.creado', 'usuarios.rol_cambiado');

    $this->getJson('/api/v1/auditoria?evento=usuarios.creado&entidad=usuarios&fecha_desde='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.auditorias.0.evento', 'usuarios.creado')
        ->assertJsonMissingPath('data.auditorias.0.valores_nuevos.password')
        ->assertJsonMissingPath('data.auditorias.0.valores_nuevos.contrasena_temporal')
        ->assertDontSee('Password123!');
});

test('registra inicios fallidos exitosos y bloqueos sin datos sensibles', function () {
    $colegio = crearColegioB2('colegio-a');
    $usuario = crearUsuarioB2($colegio, 'docente@example.test', 'Docente');

    for ($intento = 0; $intento < 5; $intento++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'incorrecta',
            'subdominio' => $colegio->subdominio,
        ]);
    }

    expect(Auditoria::query()->where('evento', 'auth.login_fallido')->count())->toBe(5)
        ->and(Auditoria::query()->where('evento', 'auth.cuenta_bloqueada')->count())->toBe(1);

    $this->assertDatabaseMissing('auditorias', ['valores_nuevos' => json_encode(['password' => 'incorrecta'])]);
});

test('rechaza actualizar y eliminar registros de auditoria', function () {
    $colegio = crearColegioB2('colegio-a');
    $usuario = crearUsuarioB2($colegio, 'director@example.test', 'Director', permisosGestionB2());
    $auditoria = Auditoria::query()->create([
        'colegio_id' => $colegio->id,
        'usuario_id' => $usuario->id,
        'evento' => 'prueba',
        'entidad' => 'usuarios',
        'entidad_id' => $usuario->id,
        'valores_anteriores' => null,
        'valores_nuevos' => ['activo' => true],
        'ip' => '127.0.0.1',
        'user_agent' => 'Prueba',
        'fecha' => now(),
    ]);

    expect(fn () => $auditoria->update(['evento' => 'alterado']))
        ->toThrow(LogicException::class)
        ->and(fn () => $auditoria->delete())
        ->toThrow(LogicException::class);
});

test('un colegio no puede consultar ni modificar usuarios o auditorias de otro colegio', function () {
    $colegioA = crearColegioB2('colegio-a');
    $colegioB = crearColegioB2('colegio-b');
    $directorA = crearUsuarioB2($colegioA, 'director-a@example.test', 'Director', permisosGestionB2());
    $directorB = crearUsuarioB2($colegioB, 'director-b@example.test', 'Director', permisosGestionB2());
    $usuarioB = crearUsuarioB2($colegioB, 'docente-b@example.test', 'Docente');
    $this->actingAs($directorB, 'sanctum')
        ->postJson('/api/v1/usuarios', [
            'nombre' => 'Otro',
            'apellido' => 'Docente',
            'email' => 'otro-b@example.test',
            'rol' => 'Docente',
        ])->assertCreated();

    $this->actingAs($directorA, 'sanctum')
        ->getJson('/api/v1/usuarios/'.$usuarioB->id)
        ->assertNotFound();

    $this->patchJson('/api/v1/usuarios/'.$usuarioB->id, ['nombre' => 'Modificado'])
        ->assertNotFound();

    $this->getJson('/api/v1/auditoria?usuario_id='.$directorB->id)
        ->assertUnprocessable();

    $this->getJson('/api/v1/auditoria')
        ->assertOk()
        ->assertJsonMissing(['usuario_id' => $directorB->id]);

    expect($usuarioB->fresh()->nombre)->toBe('Usuario');
});

test('los seeders asignan permisos granulares y exigen cambiar contrasenas iniciales', function () {
    $this->seed();

    $colegio = Colegio::query()->where('subdominio', 'salesian-innova')->firstOrFail();
    $administrador = Usuario::query()->where('email', 'administrador@salesian-innova.test')->firstOrFail();

    setPermissionsTeamId($colegio->id);
    expect($administrador->hasPermissionTo('usuarios.crear', 'web'))->toBeTrue()
        ->and($administrador->hasPermissionTo('auditoria.ver', 'web'))->toBeTrue()
        ->and($administrador->hasPermissionTo('plataforma.colegios.gestionar', 'web'))->toBeFalse()
        ->and($administrador->requiere_cambio_contrasena)->toBeTrue();
    setPermissionsTeamId(null);
});
