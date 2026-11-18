<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\Descuento;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Support\Monto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarDescuentos
{
    public function __construct(private EstadoDeCuota $estadoDeCuota) {}

    /**
     * @param  array{tipo: string, modo: string, valor: int|float|string, motivo: string}  $datos
     */
    public function aplicarACuota(Usuario $actor, Cuota $modelo, array $datos): Descuento
    {
        return DB::transaction(function () use ($actor, $modelo, $datos): Descuento {
            $cuota = Cuota::query()
                ->where('colegio_id', $actor->colegio_id)
                ->with('descuentos')
                ->lockForUpdate()
                ->findOrFail($modelo->id);

            return $this->crearDescuento($actor, $cuota, $datos);
        }, attempts: 5);
    }

    /**
     * @param  array{tipo: string, modo: string, valor: int|float|string, motivo: string}  $datos
     * @return Collection<int, Descuento>
     */
    public function aplicarAMatricula(Usuario $actor, Matricula $matricula, array $datos): Collection
    {
        return DB::transaction(function () use ($actor, $matricula, $datos): Collection {
            $cuotas = Cuota::query()
                ->where('colegio_id', $actor->colegio_id)
                ->where('matricula_id', $matricula->id)
                ->where('concepto', 'pension')
                ->whereNull('anulada_en')
                ->with('descuentos')
                ->orderBy('vencimiento')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $descuentos = new Collection;
            $estados = $this->estadoDeCuota->calcularVarias($cuotas);

            foreach ($cuotas as $cuota) {
                $estado = $estados[$cuota->id];

                if (! in_array($estado['estado'], ['pendiente', 'parcial'], true)) {
                    continue;
                }

                if (Monto::aCentimos($estado['saldo']) === 0) {
                    continue;
                }

                $descuentos->push($this->crearDescuento($actor, $cuota, $datos));
            }

            return $descuentos;
        }, attempts: 5);
    }

    public function anular(Usuario $actor, Descuento $modelo, string $motivo): Descuento
    {
        return DB::transaction(function () use ($actor, $modelo, $motivo): Descuento {
            $descuento = Descuento::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($modelo->id);

            if ($descuento->anulada_en !== null) {
                throw ValidationException::withMessages([
                    'descuento' => ['El descuento ya fue anulado.'],
                ]);
            }

            $descuento->forceFill([
                'anulada_en' => now(),
                'motivo_anulacion' => $motivo,
                'anulado_por_usuario_id' => $actor->id,
            ])->save();

            return $descuento->refresh();
        }, attempts: 5);
    }

    /**
     * @param  array{tipo: string, modo: string, valor: int|float|string, motivo: string}  $datos
     */
    private function crearDescuento(Usuario $actor, Cuota $cuota, array $datos): Descuento
    {
        if ($cuota->anulada_en !== null) {
            throw ValidationException::withMessages([
                'cuota' => ['No se puede descontar una cuota anulada.'],
            ]);
        }

        $valorCentimos = Monto::aCentimos($datos['valor']);
        $montoCalculado = $datos['modo'] === 'porcentaje'
            ? Monto::calcularPorcentaje(Monto::aCentimos($cuota->monto_original), $datos['valor'])
            : $valorCentimos;

        if ($montoCalculado <= 0) {
            throw ValidationException::withMessages([
                'valor' => ['El descuento debe calcular un monto mayor a cero.'],
            ]);
        }

        if ($montoCalculado > Monto::aCentimos($this->estadoDeCuota->calcular($cuota)['saldo'])) {
            throw ValidationException::withMessages([
                'valor' => ['El descuento no puede superar el saldo pendiente de la cuota.'],
            ]);
        }

        $descuentosActuales = $cuota->getRelation('descuentos')
            ->whereNull('anulada_en')
            ->sum(fn (Descuento $descuento): int => Monto::aCentimos($descuento->monto_calculado));

        if ($descuentosActuales + $montoCalculado > Monto::aCentimos($cuota->monto_original)) {
            throw ValidationException::withMessages([
                'valor' => ['El total de descuentos no puede superar el monto original de la cuota.'],
            ]);
        }

        return $cuota->descuentos()->create([
            'colegio_id' => $actor->colegio_id,
            'tipo' => $datos['tipo'],
            'modo' => $datos['modo'],
            'valor' => Monto::desdeCentimos($valorCentimos),
            'monto_calculado' => Monto::desdeCentimos($montoCalculado),
            'motivo' => $datos['motivo'],
            'usuario_id' => $actor->id,
        ]);
    }
}
