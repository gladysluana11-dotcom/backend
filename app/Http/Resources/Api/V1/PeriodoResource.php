<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Periodo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Periodo */
class PeriodoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'anio_escolar_id' => $this->anio_escolar_id,
            'nombre' => $this->nombre,
            'orden' => $this->orden,
            'fecha_inicio' => Carbon::parse($this->fecha_inicio)->toDateString(),
            'fecha_fin' => Carbon::parse($this->fecha_fin)->toDateString(),
            'activo' => $this->activo,
        ];
    }
}
