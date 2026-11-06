<?php

namespace Database\Seeders;

use App\Models\Colegio;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'plataforma.colegios.gestionar',
            'usuarios.gestionar',
            'alumnos.ver',
            'alumnos.crear',
            'alumnos.editar',
            'matriculas.ver',
            'matriculas.gestionar',
            'pagos.ver',
            'pagos.registrar',
            'reportes.ver',
            'avance.registrar',
            'asistencia.registrar',
            'portal.consultar',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $colegio = Colegio::query()->where('subdominio', 'salesian-innova')->firstOrFail();
        $porRol = [
            'Superadministrador' => $permisos,
            'Director' => array_values(array_diff($permisos, ['plataforma.colegios.gestionar'])),
            'Administrador' => [
                'usuarios.gestionar',
                'alumnos.ver',
                'alumnos.crear',
                'alumnos.editar',
                'matriculas.ver',
                'matriculas.gestionar',
                'reportes.ver',
            ],
            'Tesorería' => ['alumnos.ver', 'pagos.ver', 'pagos.registrar', 'reportes.ver'],
            'Coordinación' => [
                'alumnos.ver',
                'matriculas.ver',
                'reportes.ver',
                'avance.registrar',
                'asistencia.registrar',
            ],
            'Docente' => ['avance.registrar', 'asistencia.registrar'],
            'Auxiliar' => ['avance.registrar', 'asistencia.registrar'],
            'Apoderado' => ['portal.consultar'],
        ];

        setPermissionsTeamId(null);
        $superadministrador = Role::findOrCreate('Superadministrador', 'web');
        $superadministrador->syncPermissions($permisos);

        setPermissionsTeamId($colegio->id);

        foreach (array_slice($porRol, 1, null, true) as $nombre => $nombresPermisos) {
            $rol = Role::findOrCreate($nombre, 'web');
            $rol->syncPermissions($nombresPermisos);
        }

        setPermissionsTeamId(null);
    }
}
