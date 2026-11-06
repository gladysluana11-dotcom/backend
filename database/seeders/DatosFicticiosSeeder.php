<?php

namespace Database\Seeders;

use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Apoderado;
use App\Models\AsignacionDocente;
use App\Models\AsignacionSeccion;
use App\Models\Colegio;
use App\Models\ConfiguracionColegio;
use App\Models\Curso;
use App\Models\FuncionColegio;
use App\Models\Grado;
use App\Models\HistorialEstadoAlumno;
use App\Models\HistorialMatricula;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Periodo;
use App\Models\Seccion;
use App\Models\UnidadDidactica;
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

        $anio = AnioEscolar::query()->firstOrCreate(
            ['colegio_id' => $colegio->id, 'nombre' => '2026'],
            [
                'fecha_inicio' => '2026-03-02',
                'fecha_fin' => '2026-12-18',
                'activo' => true,
            ],
        );

        ConfiguracionColegio::query()->firstOrCreate(
            ['colegio_id' => $colegio->id],
            [
                'numero_cuotas_pension' => 10,
                'dia_vencimiento' => 5,
                'serie_recibos' => 'R001',
                'frecuencia_registro_avance' => 'semanal',
                'recargo_mora_activo' => false,
            ],
        );
        FuncionColegio::query()->firstOrCreate(
            ['colegio_id' => $colegio->id],
            [
                'portal_apoderados' => false,
                'notificaciones_push' => false,
                'recargo_mora' => false,
            ],
        );

        $periodos = [
            ['I Bimestre', 1, '2026-03-02', '2026-05-08', '2026-03-02', '2026-04-06', '2026-04-03', '2026-05-08'],
            ['II Bimestre', 2, '2026-05-11', '2026-07-24', '2026-05-11', '2026-06-15', '2026-06-12', '2026-07-24'],
            ['III Bimestre', 3, '2026-08-03', '2026-10-09', '2026-08-03', '2026-09-07', '2026-09-04', '2026-10-09'],
            ['IV Bimestre', 4, '2026-10-12', '2026-12-18', '2026-10-12', '2026-11-16', '2026-11-13', '2026-12-18'],
        ];

        foreach ($periodos as [$nombre, $orden, $inicio, $fin, $inicioUnidad1, $inicioUnidad2, $finUnidad1, $finUnidad2]) {
            $periodo = Periodo::query()->firstOrCreate(
                ['colegio_id' => $colegio->id, 'anio_escolar_id' => $anio->id, 'nombre' => $nombre],
                [
                    'orden' => $orden,
                    'fecha_inicio' => $inicio,
                    'fecha_fin' => $fin,
                    'activo' => true,
                ],
            );

            foreach ([
                ['Unidad 1', 1, $inicioUnidad1, $finUnidad1],
                ['Unidad 2', 2, $inicioUnidad2, $finUnidad2],
            ] as [$nombreUnidad, $ordenUnidad, $inicioUnidad, $finUnidad]) {
                UnidadDidactica::query()->firstOrCreate(
                    [
                        'colegio_id' => $colegio->id,
                        'periodo_id' => $periodo->id,
                        'nombre' => $nombreUnidad,
                    ],
                    [
                        'orden' => $ordenUnidad,
                        'fecha_inicio' => $inicioUnidad,
                        'fecha_fin' => $finUnidad,
                        'activo' => true,
                    ],
                );
            }
        }

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
                            'anio_escolar_id' => $anio->id,
                            'grado_id' => $grado->id,
                            'nombre' => $nombreSeccion,
                        ],
                        ['capacidad' => 30, 'activo' => true],
                    );
                }

                $cursosPorNivel = [
                    'Primaria' => ['Comunicación', 'Matemática', 'Personal Social', 'Ciencia y Tecnología', 'Inglés'],
                    'Secundaria' => ['Comunicación', 'Matemática', 'Ciencia y Tecnología', 'Ciencias Sociales', 'Inglés'],
                ];

                foreach ($cursosPorNivel[$ordenNivel] ?? [] as $nombreCurso) {
                    Curso::query()->firstOrCreate(
                        [
                            'colegio_id' => $colegio->id,
                            'nivel_id' => $nivel->id,
                            'grado_id' => $grado->id,
                            'nombre' => $nombreCurso,
                        ],
                        ['activo' => true],
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

        $docente = Usuario::query()->where('email', 'docente@salesian-innova.test')->firstOrFail();
        $gradoPrimaria = Grado::query()
            ->where('colegio_id', $colegio->id)
            ->whereHas('nivel', fn ($query) => $query->where('nombre', 'Primaria'))
            ->where('nombre', '1.°')
            ->firstOrFail();
        $seccionPrimaria = Seccion::query()
            ->where('colegio_id', $colegio->id)
            ->where('anio_escolar_id', $anio->id)
            ->where('grado_id', $gradoPrimaria->id)
            ->where('nombre', 'A')
            ->firstOrFail();
        $cursoPrimaria = Curso::query()
            ->where('colegio_id', $colegio->id)
            ->where('grado_id', $gradoPrimaria->id)
            ->where('nombre', 'Matemática')
            ->firstOrFail();

        AsignacionDocente::query()->firstOrCreate(
            [
                'colegio_id' => $colegio->id,
                'anio_escolar_id' => $anio->id,
                'curso_id' => $cursoPrimaria->id,
                'seccion_id' => $seccionPrimaria->id,
            ],
            ['docente_id' => $docente->id, 'activo' => true],
        );

        AsignacionSeccion::query()->firstOrCreate(
            [
                'colegio_id' => $colegio->id,
                'anio_escolar_id' => $anio->id,
                'seccion_id' => $seccionPrimaria->id,
                'usuario_id' => $docente->id,
            ],
            ['activo' => true],
        );

        $nombres = [
            'Alma', 'Bruno', 'Carla', 'Dario', 'Elena', 'Fabian', 'Gabriela', 'Hugo', 'Ines', 'Javier',
            'Kiara', 'Leonardo', 'Marina', 'Nicolas', 'Olivia', 'Pablo', 'Renata', 'Samuel', 'Tamara', 'Victor',
        ];
        $apellidos = [
            'Rios', 'Salas', 'Vega', 'Paredes', 'Campos', 'Reyes', 'Flores', 'Mendoza', 'Castro', 'Rojas',
            'Soto', 'Cruz', 'Silva', 'Navarro', 'Herrera', 'Vargas', 'Medina', 'Aguilar', 'Lopez', 'Torres',
        ];
        $secciones = Seccion::query()
            ->where('colegio_id', $colegio->id)
            ->where('anio_escolar_id', $anio->id)
            ->where('activo', true)
            ->with('grado:id,nombre')
            ->orderBy('id')
            ->get();
        $director = Usuario::query()->where('email', 'director@salesian-innova.test')->firstOrFail();

        for ($indice = 0; $indice < 40; $indice++) {
            $apellido = $apellidos[$indice % count($apellidos)];
            $alumno = Alumno::query()->firstOrCreate(
                [
                    'colegio_id' => $colegio->id,
                    'documento_identidad' => (string) (90000000 + $indice + 1),
                ],
                [
                    'nombre' => $nombres[$indice % count($nombres)],
                    'apellido' => $apellido,
                    'tipo_documento' => 'DNI',
                    'fecha_nacimiento' => sprintf('%04d-%02d-%02d', 2010 + ($indice % 7), ($indice % 12) + 1, ($indice % 27) + 1),
                    'sexo' => $indice % 2 === 0 ? 'F' : 'M',
                    'direccion' => 'Dirección ficticia '.($indice + 1),
                    'estado' => 'activo',
                    'activo' => true,
                ],
            );

            if ($alumno->wasRecentlyCreated) {
                HistorialEstadoAlumno::query()->create([
                    'colegio_id' => $colegio->id,
                    'alumno_id' => $alumno->id,
                    'estado_anterior' => null,
                    'estado_nuevo' => 'activo',
                    'fecha' => '2026-03-02',
                    'usuario_id' => $director->id,
                ]);
            }

            $grupoApoderado = intdiv($indice, 4);
            $apoderado = Apoderado::query()->firstOrCreate(
                [
                    'colegio_id' => $colegio->id,
                    'nombre' => 'Apoderado Ficticio '.($grupoApoderado + 1),
                    'apellido' => 'Familia '.($grupoApoderado + 1),
                ],
            );
            $alumno->apoderados()->syncWithoutDetaching([
                $apoderado->id => [
                    'colegio_id' => $colegio->id,
                    'parentesco' => 'Madre',
                    'telefono' => (string) (900000000 + $grupoApoderado + 1),
                    'email' => 'apoderado'.str_pad((string) ($grupoApoderado + 1), 2, '0', STR_PAD_LEFT).'@ejemplo.test',
                    'responsable_pago' => true,
                ],
            ]);

            if ($secciones->isEmpty()) {
                continue;
            }

            $seccion = $secciones[$indice % $secciones->count()];
            $matricula = Matricula::query()->firstOrCreate(
                [
                    'colegio_id' => $colegio->id,
                    'alumno_id' => $alumno->id,
                    'anio_escolar_id' => $anio->id,
                ],
                [
                    'grado_id' => $seccion->grado_id,
                    'seccion_id' => $seccion->id,
                    'usuario_id' => $director->id,
                    'anio_escolar' => $anio->nombre,
                    'grado' => $seccion->grado->nombre,
                    'seccion' => $seccion->nombre,
                    'fecha_matricula' => '2026-03-02',
                    'estado' => 'matriculado',
                ],
            );

            if ($matricula->wasRecentlyCreated) {
                HistorialMatricula::query()->create([
                    'colegio_id' => $colegio->id,
                    'matricula_id' => $matricula->id,
                    'evento' => 'creada',
                    'estado_nuevo' => 'matriculado',
                    'fecha' => '2026-03-02',
                    'usuario_id' => $director->id,
                ]);
            }
        }

        setPermissionsTeamId(null);
    }
}
