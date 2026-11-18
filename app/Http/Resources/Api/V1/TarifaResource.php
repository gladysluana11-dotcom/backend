<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Tarifa;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tarifa */
#[SchemaName('Tarifa')]
class TarifaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'anio_escolar_id' => $this->anio_escolar_id,
            'grado_id' => $this->grado_id,
            'grado' => $this->whenLoaded('grado', fn (): string => $this->grado->nombre),
            'monto_matricula' => $this->monto_matricula,
            'monto_pension' => $this->monto_pension,
            'numero_cuotas' => $this->numero_cuotas,
            'dia_vencimiento' => $this->dia_vencimiento,
            'mes_primera_pension' => $this->mes_primera_pension,
        ];
    }
}
