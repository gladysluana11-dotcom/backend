<?php

namespace App\Services;

use App\Models\Matricula;
use App\Models\Pago;
use App\Models\Usuario;
use App\Support\Monto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ConsultarReportes
{
    public function __construct(private ConsultarDeudas $consultarDeudas) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{resumen: array<string, mixed>, filas: list<array<string, mixed>>, filtros: array<string, mixed>}
     */
    public function matriculas(Usuario $actor, array $filtros): array
    {
        $matriculas = Matricula::query()
            ->where('colegio_id', $actor->colegio_id)
            ->whereIn('estado', ['pre_inscrito', 'pendiente_documentos', 'matriculado'])
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
            ))
            ->when(isset($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->when(isset($filtros['buscar']), fn (Builder $query) => $query->whereHas(
                'alumno',
                fn (Builder $alumnos) => $alumnos->where(function (Builder $buscar) use ($filtros): void {
                    $texto = '%'.trim((string) $filtros['buscar']).'%';
                    $buscar->where('nombre', 'like', $texto)->orWhere('apellido', 'like', $texto);
                }),
            ))
            ->with([
                'alumno:id,colegio_id,nombre,apellido',
                'anioEscolar:id,nombre',
                'gradoRegistro:id,colegio_id,nivel_id,nombre',
                'gradoRegistro.nivel:id,colegio_id,nombre',
                'seccionRegistro:id,colegio_id,nombre',
            ])
            ->withCount(['documentos as documentos_pendientes' => fn (Builder $query) => $query
                ->where('estado', '!=', 'entregado')])
            ->orderBy('anio_escolar_id')
            ->orderBy('grado_id')
            ->orderBy('seccion_id')
            ->orderBy('alumno_id')
            ->get();

        $filas = $matriculas->map(fn (Matricula $matricula): array => [
            'matricula_id' => (int) $matricula->id,
            'alumno_id' => (int) $matricula->alumno_id,
            'nombre' => $matricula->alumno->nombre,
            'apellido' => $matricula->alumno->apellido,
            'anio_escolar' => $matricula->anioEscolar?->nombre,
            'nivel' => $matricula->gradoRegistro?->nivel?->nombre,
            'grado' => $matricula->gradoRegistro?->nombre,
            'seccion' => $matricula->seccionRegistro?->nombre,
            'estado' => $matricula->estado,
            'documentos_pendientes' => (int) $matricula->documentos_pendientes,
        ])->all();

        return [
            'resumen' => [
                'total_matriculas' => count($filas),
                'pendientes_completar' => count(array_filter(
                    $filas,
                    fn (array $fila): bool => $fila['estado'] !== 'matriculado' || $fila['documentos_pendientes'] > 0,
                )),
            ],
            'filas' => $filas,
            'filtros' => $this->filtrosAplicados($filtros),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{resumen: array<string, mixed>, filas: list<array<string, mixed>>, filtros: array<string, mixed>}
     */
    public function pagos(Usuario $actor, array $filtros): array
    {
        $pagos = Pago::query()
            ->where('colegio_id', $actor->colegio_id)
            ->when(isset($filtros['desde']), fn (Builder $query) => $query->whereDate('fecha_pago', '>=', $filtros['desde']))
            ->when(isset($filtros['hasta']), fn (Builder $query) => $query->whereDate('fecha_pago', '<=', $filtros['hasta']))
            ->when(isset($filtros['medio']), fn (Builder $query) => $query->where('medio', $filtros['medio']))
            ->when(isset($filtros['usuario_id']), fn (Builder $query) => $query->where('usuario_id', $filtros['usuario_id']))
            ->when(isset($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->with([
                'alumno:id,colegio_id,nombre,apellido',
                'usuario:id,nombre,apellido',
            ])
            ->orderBy('fecha_pago')
            ->orderBy('id')
            ->get();

        $filas = $pagos->map(fn (Pago $pago): array => [
            'pago_id' => (int) $pago->id,
            'fecha_pago' => $pago->fecha_pago->toDateString(),
            'alumno' => trim($pago->alumno->nombre.' '.$pago->alumno->apellido),
            'usuario' => trim($pago->usuario->nombre.' '.$pago->usuario->apellido),
            'medio' => $pago->medio,
            'monto' => $pago->monto,
            'estado' => $pago->estado,
            'constancia' => $pago->constancia,
        ])->all();
        $totalVigenteCentimos = $pagos
            ->where('estado', 'vigente')
            ->sum(fn (Pago $pago): int => Monto::aCentimos($pago->monto));

        return [
            'resumen' => [
                'cantidad' => count($filas),
                'total_vigente' => Monto::desdeCentimos($totalVigenteCentimos),
            ],
            'filas' => $filas,
            'filtros' => $this->filtrosAplicados($filtros),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{resumen: array<string, mixed>, filas: list<array<string, mixed>>, filtros: array<string, mixed>}
     */
    public function ingresosMensuales(Usuario $actor, array $filtros): array
    {
        $desde = $filtros['desde'] ?? today()->startOfYear()->toDateString();
        $hasta = $filtros['hasta'] ?? today()->endOfYear()->toDateString();
        $descuentos = DB::table('descuentos')
            ->select('colegio_id', 'cuota_id')
            ->selectRaw('SUM(monto_calculado) as total_descuentos')
            ->where('colegio_id', $actor->colegio_id)
            ->whereNull('anulada_en')
            ->groupBy('colegio_id', 'cuota_id');
        $emitidos = DB::table('cuotas')
            ->leftJoinSub($descuentos, 'descuentos_activos', function ($join): void {
                $join->on('descuentos_activos.cuota_id', '=', 'cuotas.id')
                    ->on('descuentos_activos.colegio_id', '=', 'cuotas.colegio_id');
            })
            ->where('cuotas.colegio_id', $actor->colegio_id)
            ->whereNull('cuotas.anulada_en')
            ->whereBetween('cuotas.vencimiento', [$desde, $hasta])
            ->when(
                isset($filtros['anio_escolar_id']),
                fn ($query) => $query->where('cuotas.anio_escolar_id', $filtros['anio_escolar_id']),
            )
            ->selectRaw("DATE_FORMAT(cuotas.vencimiento, '%Y-%m') as mes")
            ->selectRaw('SUM(cuotas.monto_original - COALESCE(descuentos_activos.total_descuentos, 0)) as monto')
            ->groupBy('mes')
            ->pluck('monto', 'mes');
        $cobrados = DB::table('aplicacion_pagos')
            ->join('cuotas as cuotas_aplicadas', function ($join): void {
                $join->on('cuotas_aplicadas.id', '=', 'aplicacion_pagos.cuota_id')
                    ->on('cuotas_aplicadas.colegio_id', '=', 'aplicacion_pagos.colegio_id');
            })
            ->join('pagos', function ($join): void {
                $join->on('pagos.id', '=', 'aplicacion_pagos.pago_id')
                    ->on('pagos.colegio_id', '=', 'aplicacion_pagos.colegio_id');
            })
            ->where('pagos.colegio_id', $actor->colegio_id)
            ->where('pagos.estado', 'vigente')
            ->whereNull('cuotas_aplicadas.anulada_en')
            ->whereBetween('pagos.fecha_pago', [$desde, $hasta])
            ->when(
                isset($filtros['anio_escolar_id']),
                fn ($query) => $query->where('cuotas_aplicadas.anio_escolar_id', $filtros['anio_escolar_id']),
            )
            ->when(isset($filtros['medio']), fn ($query) => $query->where('pagos.medio', $filtros['medio']))
            ->when(isset($filtros['usuario_id']), fn ($query) => $query->where('pagos.usuario_id', $filtros['usuario_id']))
            ->selectRaw("DATE_FORMAT(pagos.fecha_pago, '%Y-%m') as mes")
            ->selectRaw('SUM(aplicacion_pagos.monto_aplicado) as monto')
            ->groupBy('mes')
            ->pluck('monto', 'mes');
        $meses = $emitidos->keys()->merge($cobrados->keys())->unique()->sort()->values();
        $filas = $meses->map(fn (string $mes): array => [
            'mes' => $mes,
            'cuotas_emitidas' => Monto::desdeCentimos(Monto::aCentimos($emitidos->get($mes, '0.00'))),
            'cuotas_cobradas' => Monto::desdeCentimos(Monto::aCentimos($cobrados->get($mes, '0.00'))),
        ])->all();

        return [
            'resumen' => [
                'total_emitido' => Monto::desdeCentimos(array_sum(array_map(
                    fn (array $fila): int => Monto::aCentimos($fila['cuotas_emitidas']),
                    $filas,
                ))),
                'total_cobrado' => Monto::desdeCentimos(array_sum(array_map(
                    fn (array $fila): int => Monto::aCentimos($fila['cuotas_cobradas']),
                    $filas,
                ))),
            ],
            'filas' => $filas,
            'filtros' => $this->filtrosAplicados([...$filtros, 'desde' => $desde, 'hasta' => $hasta]),
        ];
    }

    /**
     * @return array{resumen: array<string, mixed>, filas: list<array<string, mixed>>, filtros: array<string, mixed>}
     */
    public function dashboard(Usuario $actor): array
    {
        $matriculadosPorNivel = DB::table('matriculas')
            ->join('grados', function ($join): void {
                $join->on('grados.id', '=', 'matriculas.grado_id')
                    ->on('grados.colegio_id', '=', 'matriculas.colegio_id');
            })
            ->join('niveles', function ($join): void {
                $join->on('niveles.id', '=', 'grados.nivel_id')
                    ->on('niveles.colegio_id', '=', 'grados.colegio_id');
            })
            ->where('matriculas.colegio_id', $actor->colegio_id)
            ->where('matriculas.estado', 'matriculado')
            ->select('niveles.id as nivel_id', 'niveles.nombre as nivel')
            ->selectRaw('COUNT(matriculas.id) as matriculados')
            ->groupBy('niveles.id', 'niveles.nombre')
            ->orderBy('niveles.id')
            ->get()
            ->map(fn (object $fila): array => [
                'nivel_id' => (int) $fila->nivel_id,
                'nivel' => $fila->nivel,
                'matriculados' => (int) $fila->matriculados,
            ])->all();
        $inicioMes = today()->startOfMonth()->toDateString();
        $finMes = today()->endOfMonth()->toDateString();
        $recaudacionMes = Pago::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('estado', 'vigente')
            ->whereBetween('fecha_pago', [$inicioMes, $finMes])
            ->sum('monto');
        $deudas = $this->consultarDeudas->resumenGeneral($actor);
        $totalEmitidoCentimos = Monto::aCentimos($deudas['total_emitido']);
        $porcentajeMorosidad = $totalEmitidoCentimos === 0
            ? '0.00'
            : number_format(Monto::aCentimos($deudas['total_vencido']) / $totalEmitidoCentimos * 100, 2, '.', '');

        return [
            'resumen' => [
                'matriculados_por_nivel' => $matriculadosPorNivel,
                'recaudacion_mes' => Monto::desdeCentimos(Monto::aCentimos($recaudacionMes ?: '0.00')),
                'deuda_total' => $deudas['total_pendiente'],
                'deuda_vencida' => $deudas['total_vencido'],
                'porcentaje_morosidad' => $porcentajeMorosidad,
            ],
            'filas' => $matriculadosPorNivel,
            'filtros' => ['mes' => today()->format('Y-m')],
        ];
    }

    /** @param array<string, mixed> $filtros
     * @return array<string, mixed>
     */
    private function filtrosAplicados(array $filtros): array
    {
        unset($filtros['formato'], $filtros['colegio_id']);

        return array_filter($filtros, fn (mixed $valor): bool => $valor !== null);
    }
}
