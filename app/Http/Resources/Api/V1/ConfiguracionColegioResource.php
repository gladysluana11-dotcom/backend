<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ConfiguracionColegio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConfiguracionColegio */
class ConfiguracionColegioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'numero_cuotas_pension' => $this->numero_cuotas_pension,
            'dia_vencimiento' => $this->dia_vencimiento,
            'serie_recibos' => $this->serie_recibos,
            'frecuencia_registro_avance' => $this->frecuencia_registro_avance,
            'recargo_mora_activo' => $this->recargo_mora_activo,
        ];
    }
}
