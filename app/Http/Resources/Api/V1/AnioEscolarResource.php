<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AnioEscolar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin AnioEscolar */
class AnioEscolarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'fecha_inicio' => $this->fecha_inicio === null ? null : Carbon::parse($this->fecha_inicio)->toDateString(),
            'fecha_fin' => $this->fecha_fin === null ? null : Carbon::parse($this->fecha_fin)->toDateString(),
            'activo' => $this->activo,
            'cerrado' => $this->cerrado,
        ];
    }
}
