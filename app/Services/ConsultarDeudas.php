<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\Usuario;
use App\Support\Monto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type ContactoResponsable array{nombre: string, apellido: string, telefono: string|null, email: string|null}
 * @phpstan-type Deuda array{
 *   alumno_id: int,
 *   nombre: string,
 *   apellido: string,
 *   nivel: string|null,
 *   grado: string|null,
 *   seccion: string|null,
 *   seccion_id: int,
 *   total_vencido: string,
 *   total_pendiente: string,
 *   cuotas_vencidas: int,
 *   dias_atraso: int,
 *   apoderado_responsable_pago: ContactoResponsable|null
 * }
 */
class ConsultarDeudas
{
    public function __construct(private EstadoDeCuota $estadoDeCuota) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Deuda>
     */
    public function listar(Usuario $actor, array $filtros): LengthAwarePaginator
    {
        $deudas = $this->construirDeudas($actor, $filtros);
        $porPagina = (int) ($filtros['per_page'] ?? 25);
        $pagina = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $deudas->forPage($pagina, $porPagina)->values(),
            $deudas->count(),
            $porPagina,
            $pagina,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function porAula(Usuario $actor, array $filtros): array
    {
        return $this->construirDeudas($actor, $filtros)
            ->groupBy('seccion_id')
            ->map(function (Collection $alumnos, int|string $seccionId): array {
                $primero = $alumnos->first();

                return [
                    'seccion_id' => (int) $seccionId,
                    'nivel' => $primero['nivel'],
                    'grado' => $primero['grado'],
                    'seccion' => $primero['seccion'],
                    'cantidad_alumnos' => $alumnos->count(),
                    'total_vencido' => Monto::desdeCentimos($alumnos->sum(
                        fn (array $alumno): int => Monto::aCentimos($alumno['total_vencido']),
                    )),
                    'total_pendiente' => Monto::desdeCentimos($alumnos->sum(
                        fn (array $alumno): int => Monto::aCentimos($alumno['total_pendiente']),
                    )),
                    'alumnos' => $alumnos->values()->all(),
                ];
            })
            ->sortBy(fn (array $aula): string => mb_strtolower($aula['nivel'].' '.$aula['grado'].' '.$aula['seccion']))
            ->values()
            ->all();
    }

    /**
     * @return array{total_pendiente: string, total_vencido: string, total_emitido: string, cuotas_vencidas: int}
     */
    public function resumenGeneral(Usuario $actor): array
    {
        $cuotas = $this->consultarCuotas($actor, cargarContexto: false);
        $estados = $this->estadoDeCuota->calcularVarias($cuotas);
        $totalPendiente = 0;
        $totalVencido = 0;
        $totalEmitido = 0;
        $cuotasVencidas = 0;

        foreach ($cuotas as $cuota) {
            $estado = $estados[$cuota->id];
            $saldo = Monto::aCentimos($estado['saldo']);
            $totalPendiente += $saldo;
            $totalEmitido += Monto::aCentimos($cuota->monto_original)
                - Monto::aCentimos($estado['total_descuentos']);

            if ($saldo > 0 && $cuota->vencimiento->toDateString() < today()->toDateString()) {
                $totalVencido += $saldo;
                $cuotasVencidas++;
            }
        }

        return [
            'total_pendiente' => Monto::desdeCentimos($totalPendiente),
            'total_vencido' => Monto::desdeCentimos($totalVencido),
            'total_emitido' => Monto::desdeCentimos($totalEmitido),
            'cuotas_vencidas' => $cuotasVencidas,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Deuda>
     */
    private function construirDeudas(Usuario $actor, array $filtros): Collection
    {
        $cuotas = $this->consultarCuotas($actor, $filtros, cargarContexto: true);
        $estados = $this->estadoDeCuota->calcularVarias($cuotas);
        $porAlumno = [];

        foreach ($cuotas as $cuota) {
            $estado = $estados[$cuota->id];
            $saldo = Monto::aCentimos($estado['saldo']);

            if ($saldo <= 0) {
                continue;
            }

            $alumno = $cuota->alumno;
            $matricula = $cuota->matricula;
            $grado = $matricula->gradoRegistro;
            $seccion = $matricula->seccionRegistro;
            $nivel = $grado?->nivel;
            $alumnoId = (int) $cuota->alumno_id;
            $porAlumno[$alumnoId] ??= [
                'alumno_id' => $alumnoId,
                'nombre' => $alumno->nombre,
                'apellido' => $alumno->apellido,
                'nivel' => $nivel?->nombre,
                'grado' => $grado?->nombre,
                'seccion' => $seccion?->nombre,
                'seccion_id' => (int) $matricula->seccion_id,
                'total_vencido_centimos' => 0,
                'total_pendiente_centimos' => 0,
                'cuotas_vencidas' => 0,
                'dias_atraso' => 0,
                'matricula_reciente_id' => 0,
            ];
            $porAlumno[$alumnoId]['total_pendiente_centimos'] += $saldo;

            if ((int) $matricula->id > $porAlumno[$alumnoId]['matricula_reciente_id']) {
                $porAlumno[$alumnoId]['nivel'] = $nivel?->nombre;
                $porAlumno[$alumnoId]['grado'] = $grado?->nombre;
                $porAlumno[$alumnoId]['seccion'] = $seccion?->nombre;
                $porAlumno[$alumnoId]['seccion_id'] = (int) $matricula->seccion_id;
                $porAlumno[$alumnoId]['matricula_reciente_id'] = (int) $matricula->id;
            }

            if ($cuota->vencimiento->toDateString() < today()->toDateString()) {
                $porAlumno[$alumnoId]['total_vencido_centimos'] += $saldo;
                $porAlumno[$alumnoId]['cuotas_vencidas']++;
                $porAlumno[$alumnoId]['dias_atraso'] = max(
                    $porAlumno[$alumnoId]['dias_atraso'],
                    (int) $cuota->vencimiento->diffInDays(today(), false),
                );
            }
        }

        $alumnos = collect($porAlumno)
            ->filter(fn (array $deuda): bool => $deuda['cuotas_vencidas'] > 0)
            ->map(fn (array $deuda): array => [
                'alumno_id' => $deuda['alumno_id'],
                'nombre' => $deuda['nombre'],
                'apellido' => $deuda['apellido'],
                'nivel' => $deuda['nivel'],
                'grado' => $deuda['grado'],
                'seccion' => $deuda['seccion'],
                'seccion_id' => $deuda['seccion_id'],
                'total_vencido' => Monto::desdeCentimos($deuda['total_vencido_centimos']),
                'total_pendiente' => Monto::desdeCentimos($deuda['total_pendiente_centimos']),
                'cuotas_vencidas' => $deuda['cuotas_vencidas'],
                'dias_atraso' => $deuda['dias_atraso'],
            ]);

        if ($alumnos->isEmpty()) {
            return collect();
        }

        $contactos = DB::table('alumno_apoderado as vinculos')
            ->join('apoderados', 'apoderados.id', '=', 'vinculos.apoderado_id')
            ->where('vinculos.colegio_id', $actor->colegio_id)
            ->where('vinculos.responsable_pago', true)
            ->whereIn('vinculos.alumno_id', $alumnos->keys()->all())
            ->select([
                'vinculos.alumno_id',
                'apoderados.nombre',
                'apoderados.apellido',
                'vinculos.telefono',
                'vinculos.email',
            ])
            ->orderBy('vinculos.id')
            ->get()
            ->groupBy('alumno_id')
            ->map(function (Collection $contactosAlumno): ?array {
                $contacto = $contactosAlumno->first();

                if ($contacto === null) {
                    return null;
                }

                return [
                    'nombre' => (string) $contacto->nombre,
                    'apellido' => (string) $contacto->apellido,
                    'telefono' => $contacto->telefono === null ? null : (string) $contacto->telefono,
                    'email' => $contacto->email === null ? null : (string) $contacto->email,
                ];
            });

        $buscar = mb_strtolower(trim((string) ($filtros['buscar'] ?? '')));

        $alumnos = $alumnos
            ->map(function (array $deuda, int $alumnoId) use ($contactos): array {
                $contacto = $contactos->get($alumnoId);

                return [
                    ...$deuda,
                    'apoderado_responsable_pago' => $contacto,
                ];
            })
            ->filter(function (array $deuda) use ($filtros, $buscar): bool {
                if (isset($filtros['dias_atraso_min']) && $deuda['dias_atraso'] < (int) $filtros['dias_atraso_min']) {
                    return false;
                }

                if (isset($filtros['monto_min'])
                    && Monto::aCentimos($deuda['total_vencido']) < Monto::aCentimos($filtros['monto_min'])) {
                    return false;
                }

                if ($buscar === '') {
                    return true;
                }

                $contacto = $deuda['apoderado_responsable_pago'] ?? [];
                $texto = mb_strtolower(implode(' ', array_filter([
                    $deuda['nombre'],
                    $deuda['apellido'],
                    $contacto['nombre'] ?? null,
                    $contacto['apellido'] ?? null,
                    $contacto['telefono'] ?? null,
                    $contacto['email'] ?? null,
                ])));

                return str_contains($texto, $buscar);
            });

        $orden = $filtros['orden'] ?? 'dias_atraso';
        $direccionDescendente = ($filtros['direccion'] ?? 'desc') === 'desc';

        return $alumnos
            ->sortBy(
                fn (array $deuda): string|float => $orden === 'nombre'
                    ? mb_strtolower($deuda['apellido'].' '.$deuda['nombre'])
                    : (float) $deuda[$orden],
                SORT_REGULAR,
                $direccionDescendente,
            )
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Database\Eloquent\Collection<int, Cuota>
     */
    private function consultarCuotas(Usuario $actor, array $filtros = [], bool $cargarContexto = false): \Illuminate\Database\Eloquent\Collection
    {
        $consulta = Cuota::query()
            ->where('colegio_id', $actor->colegio_id)
            ->whereNull('anulada_en')
            ->whereHas('matricula', function (Builder $matriculas) use ($filtros): void {
                $matriculas
                    ->when(
                        isset($filtros['anio_escolar_id']),
                        fn (Builder $query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']),
                    )
                    ->when(
                        isset($filtros['grado_id']),
                        fn (Builder $query) => $query->where('grado_id', $filtros['grado_id']),
                    )
                    ->when(
                        isset($filtros['seccion_id']),
                        fn (Builder $query) => $query->where('seccion_id', $filtros['seccion_id']),
                    )
                    ->when(isset($filtros['nivel_id']), fn (Builder $query) => $query->whereHas(
                        'gradoRegistro',
                        fn (Builder $grado) => $grado->where('nivel_id', $filtros['nivel_id']),
                    ));
            })
            ->with(['descuentos' => fn (HasMany $query) => $query->whereNull('anulada_en')]);

        if ($cargarContexto) {
            $consulta->with([
                'alumno:id,colegio_id,nombre,apellido',
                'matricula:id,colegio_id,alumno_id,anio_escolar_id,grado_id,seccion_id',
                'matricula.gradoRegistro:id,colegio_id,nivel_id,nombre',
                'matricula.gradoRegistro.nivel:id,colegio_id,nombre',
                'matricula.seccionRegistro:id,colegio_id,nombre',
            ]);
        }

        return $consulta->orderBy('alumno_id')->orderBy('vencimiento')->orderBy('id')->get();
    }
}
