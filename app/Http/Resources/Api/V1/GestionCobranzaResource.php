<?php

namespace App\Http\Resources\Api\V1;

use App\Models\GestionCobranza;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GestionCobranza */
#[SchemaName('GestionCobranza')]
class GestionCobranzaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alumno_id' => $this->alumno_id,
            'usuario_id' => $this->usuario_id,
            'usuario' => trim($this->usuario->nombre.' '.$this->usuario->apellido),
            'fecha' => $this->fecha->toDateString(),
            'medio_contacto' => $this->medio_contacto,
            'resultado' => $this->resultado,
            'compromiso_pago_fecha' => $this->compromiso_pago_fecha?->toDateString(),
            'compromiso_monto' => $this->compromiso_monto,
            'observacion' => $this->observacion,
        ];
    }
}
