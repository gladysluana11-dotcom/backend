<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\Matricula;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Support\Monto;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class GenerarCuotas
{
    public function __construct(private RegistrarAuditoria $auditoria) {}

    /**
     * @return Collection<int, Cuota>
     */
    public function generar(Matricula $modelo, ?Usuario $actor = null): Collection
    {
        return DB::transaction(function () use ($modelo, $actor): Collection {
            $matricula = Matricula::query()
                ->where('colegio_id', $modelo->colegio_id)
                ->lockForUpdate()
                ->findOrFail($modelo->id);

            if ($matricula->estado !== 'matriculado') {
                throw ValidationException::withMessages([
                    'matricula' => ['Solo se pueden generar cuotas para una matrícula confirmada.'],
                ]);
            }

            $tarifa = Tarifa::query()
                ->where('colegio_id', $matricula->colegio_id)
                ->where('anio_escolar_id', $matricula->anio_escolar_id)
                ->where('grado_id', $matricula->grado_id)
                ->first();

            if ($tarifa === null) {
                $this->auditoria->registrar(
                    'cuotas.generacion_sin_tarifa',
                    'matriculas',
                    (int) $matricula->id,
                    valoresNuevos: ['resultado' => 'sin_tarifa'],
                    usuario: $actor,
                    colegioId: (int) $matricula->colegio_id,
                );

                return new Collection;
            }

            $datos = [
                [
                    'concepto' => 'matricula',
                    'numero' => 1,
                    'descripcion' => 'Matrícula '.$matricula->anio_escolar,
                    'monto_original' => $tarifa->monto_matricula,
                    'vencimiento' => $matricula->fecha_matricula->toDateString(),
                ],
            ];
            $fechaPrimeraPension = CarbonImmutable::create(
                (int) $matricula->anio_escolar,
                $tarifa->mes_primera_pension,
                1,
            ) ?? throw new LogicException('No se pudo construir la fecha de la primera pensión.');
            $meses = [
                'enero',
                'febrero',
                'marzo',
                'abril',
                'mayo',
                'junio',
                'julio',
                'agosto',
                'setiembre',
                'octubre',
                'noviembre',
                'diciembre',
            ];

            for ($numero = 1; $numero <= $tarifa->numero_cuotas; $numero++) {
                $vencimiento = $fechaPrimeraPension
                    ->addMonths($numero - 1)
                    ->day($tarifa->dia_vencimiento);

                $datos[] = [
                    'concepto' => 'pension',
                    'numero' => $numero,
                    'descripcion' => 'Pensión '.$meses[$vencimiento->month - 1].' '.$vencimiento->year,
                    'monto_original' => $tarifa->monto_pension,
                    'vencimiento' => $vencimiento->toDateString(),
                ];
            }

            $cuotas = new Collection;

            foreach ($datos as $dato) {
                $cuota = Cuota::query()->firstOrCreate(
                    [
                        'colegio_id' => $matricula->colegio_id,
                        'matricula_id' => $matricula->id,
                        'concepto' => $dato['concepto'],
                        'numero' => $dato['numero'],
                    ],
                    [
                        'alumno_id' => $matricula->alumno_id,
                        'anio_escolar_id' => $matricula->anio_escolar_id,
                        'descripcion' => $dato['descripcion'],
                        'monto_original' => $dato['monto_original'],
                        'vencimiento' => $dato['vencimiento'],
                    ],
                );

                $cuotas->push($cuota);

                if ($cuota->wasRecentlyCreated && ! Auth::user() instanceof Usuario) {
                    $this->auditoria->registrar(
                        'cuotas.creada',
                        'cuotas',
                        (int) $cuota->id,
                        valoresNuevos: [
                            'matricula_id' => (int) $cuota->matricula_id,
                            'concepto' => $cuota->concepto,
                            'numero' => $cuota->numero,
                            'monto_original' => Monto::desdeCentimos(Monto::aCentimos($cuota->monto_original)),
                            'vencimiento' => $cuota->vencimiento->toDateString(),
                        ],
                        usuario: $actor,
                        colegioId: (int) $cuota->colegio_id,
                    );
                }
            }

            return $cuotas;
        }, attempts: 5);
    }
}
