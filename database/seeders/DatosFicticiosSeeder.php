<?php

namespace Database\Seeders;

use App\Models\AnioEscolar;
use App\Models\Colegio;
use App\Models\Grado;
use App\Models\Nivel;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatosFicticiosSeeder extends Seeder
{
    public function run(): void
    {
        $colegio = Colegio::query()->firstOrCreate(
            ['subdominio' => 'salesian-innova'],
            [
                'nombre' => 'Salesian Innova',
                'logo' => null,
                'colores' => [
                    'primario' => '#0F4C81',
                    'secundario' => '#F2A900',
                ],
                'direccion' => 'Dirección ficticia, Huaycán, Lima',
                'telefono' => '900000001',
                'correo_contacto' => 'contacto@salesian-innova.test',
                'activo' => true,
            ],
        );

        AnioEscolar::query()->firstOrCreate(
            ['colegio_id' => $colegio->id, 'nombre' => '2026'],
            [
                'fecha_inicio' => '2026-03-02',
                'fecha_fin' => '2026-12-18',
                'activo' => true,
            ],
        );

        $estructura = [
            'Inicial' => ['3 años', '4 años', '5 años'],
            'Primaria' => ['1.°', '2.°', '3.°', '4.°', '5.°', '6.°'],
            'Secundaria' => ['1.°', '2.°', '3.°', '4.°', '5.°'],
        ];

        foreach ($estructura as $ordenNivel => $grados) {
            $nivel = Nivel::query()->firstOrCreate(
                ['colegio_id' => $colegio->id, 'nombre' => $ordenNivel],
                ['orden' => count(Nivel::query()->where('colegio_id', $colegio->id)->get()) + 1],
            );

            foreach ($grados as $ordenGrado => $nombreGrado) {
                $grado = Grado::query()->firstOrCreate(
                    [
                        'colegio_id' => $colegio->id,
                        'nivel_id' => $nivel->id,
                        'nombre' => $nombreGrado,
                    ],
                    ['orden' => $ordenGrado + 1],
                );

                foreach (['A', 'B'] as $nombreSeccion) {
                    Seccion::query()->firstOrCreate(
                        [
                            'colegio_id' => $colegio->id,
                            'grado_id' => $grado->id,
                            'nombre' => $nombreSeccion,
                        ],
                        ['capacidad' => 30, 'activo' => true],
                    );
                }
            }
        }

        $usuarios = [
            'Superadministrador' => ['Sofía', 'Plataforma', 'superadmin@plataforma.test', null],
            'Director' => ['Diego', 'Director', 'director@salesian-innova.test', $colegio->id],
            'Administrador' => ['Andrea', 'Administración', 'administrador@salesian-innova.test', $colegio->id],
            'Tesorería' => ['Tomás', 'Tesorería', 'tesoreria@salesian-innova.test', $colegio->id],
            'Coordinación' => ['Camila', 'Coordinación', 'coordinacion@salesian-innova.test', $colegio->id],
            'Docente' => ['Daniel', 'Docente', 'docente@salesian-innova.test', $colegio->id],
            'Auxiliar' => ['Ana', 'Auxiliar', 'auxiliar@salesian-innova.test', $colegio->id],
            'Apoderado' => ['Alex', 'Apoderado', 'apoderado@salesian-innova.test', $colegio->id],
        ];

        foreach ($usuarios as $nombreRol => [$nombre, $apellido, $email, $colegioId]) {
            $usuario = Usuario::query()->firstOrCreate(
                ['email' => $email],
                [
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'password' => Hash::make('Password123!'),
                    'activo' => true,
                    'requiere_cambio_contrasena' => true,
                ],
            );

            if ($usuario->wasRecentlyCreated) {
                $usuario->forceFill(['colegio_id' => $colegioId])->save();
            } elseif (! $usuario->requiere_cambio_contrasena) {
                $usuario->forceFill(['requiere_cambio_contrasena' => true])->save();
            }

            setPermissionsTeamId($colegioId);
            $rol = Role::findOrCreate($nombreRol, 'web');
            $usuario->syncRoles([$rol]);
        }

        setPermissionsTeamId(null);
    }
}
