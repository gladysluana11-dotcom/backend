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
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.desactivar',
            'usuarios.reactivar',
            'roles.ver',
            'permisos.ver',
            'auditoria.ver',
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
            'academico.ver',
            'academico.crear',
            'academico.editar',
            'academico.desactivar',
            'academico.asignaciones.propias',
            'academico.importar',
            'configuracion.ver',
            'configuracion.editar',
            'funciones.ver',
            'funciones.editar',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $colegio = Colegio::query()->where('subdominio', 'salesian-innova')->firstOrFail();
        $porRol = [
            'Superadministrador' => $permisos,
            'Director' => array_values(array_diff($permisos, ['plataforma.colegios.gestionar'])),
            'Administrador' => [
                'usuarios.ver',
                'usuarios.crear',
                'usuarios.editar',
                'usuarios.desactivar',
                'usuarios.reactivar',
                'roles.ver',
                'permisos.ver',
                'auditoria.ver',
                'alumnos.ver',
                'alumnos.crear',
                'alumnos.editar',
                'matriculas.ver',
                'matriculas.gestionar',
                'reportes.ver',
                'academico.ver',
                'academico.crear',
                'academico.editar',
                'academico.desactivar',
                'academico.importar',
                'configuracion.ver',
                'configuracion.editar',
                'funciones.ver',
                'funciones.editar',
            ],
            'Tesorería' => ['alumnos.ver', 'pagos.ver', 'pagos.registrar', 'reportes.ver', 'configuracion.ver'],
            'Coordinación' => [
                'alumnos.ver',
                'matriculas.ver',
                'reportes.ver',
                'avance.registrar',
                'asistencia.registrar',
                'academico.ver',
                'academico.crear',
                'academico.editar',
                'academico.desactivar',
                'configuracion.ver',
            ],
            'Docente' => [
                'avance.registrar',
                'asistencia.registrar',
                'academico.ver',
                'academico.asignaciones.propias',
            ],
            'Auxiliar' => ['avance.registrar', 'asistencia.registrar', 'academico.ver'],
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
