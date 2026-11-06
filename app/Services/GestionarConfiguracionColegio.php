<?php

namespace App\Services;

use App\Models\ConfiguracionColegio;
use App\Models\FuncionColegio;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class GestionarConfiguracionColegio
{
    public function obtenerConfiguracion(Usuario $actor): ConfiguracionColegio
    {
        $this->validarColegio($actor);

        return ConfiguracionColegio::query()->firstOrCreate(
            ['colegio_id' => $actor->colegio_id],
            [
                'numero_cuotas_pension' => 10,
                'dia_vencimiento' => 5,
                'serie_recibos' => 'R001',
                'frecuencia_registro_avance' => 'semanal',
                'recargo_mora_activo' => false,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarConfiguracion(Usuario $actor, array $datos): ConfiguracionColegio
    {
        return DB::transaction(function () use ($actor, $datos): ConfiguracionColegio {
            $this->bloquearColegio($actor);
            $configuracion = ConfiguracionColegio::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->first();

            if ($configuracion === null) {
                $this->obtenerConfiguracion($actor);
                $configuracion = ConfiguracionColegio::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $configuracion->fill($datos)->save();

            return $configuracion->refresh();
        }, attempts: 5);
    }

    public function obtenerFunciones(Usuario $actor): FuncionColegio
    {
        $this->validarColegio($actor);

        return FuncionColegio::query()->firstOrCreate(
            ['colegio_id' => $actor->colegio_id],
            [
                'portal_apoderados' => false,
                'notificaciones_push' => false,
                'recargo_mora' => false,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarFunciones(Usuario $actor, array $datos): FuncionColegio
    {
        return DB::transaction(function () use ($actor, $datos): FuncionColegio {
            $this->bloquearColegio($actor);
            $funciones = FuncionColegio::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->first();

            if ($funciones === null) {
                $this->obtenerFunciones($actor);
                $funciones = FuncionColegio::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $funciones->fill($datos)->save();

            return $funciones->refresh();
        }, attempts: 5);
    }

    public function funcionActiva(Usuario $actor, string $nombre): bool
    {
        $this->validarColegio($actor);

        return FuncionColegio::estaActiva($nombre, $actor->colegio_id);
    }

    private function bloquearColegio(Usuario $actor): void
    {
        $this->validarColegio($actor);
        DB::table('colegios')->where('id', $actor->colegio_id)->lockForUpdate()->first();
    }

    private function validarColegio(Usuario $actor): void
    {
        if ($actor->colegio_id === null) {
            throw new ModelNotFoundException;
        }
    }
}
