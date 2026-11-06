<?php

namespace App\Http\Resources\Api\V1;

use App\Models\FuncionColegio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FuncionColegio */
class FuncionColegioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'portal_apoderados' => $this->portal_apoderados,
            'notificaciones_push' => $this->notificaciones_push,
            'recargo_mora' => $this->recargo_mora,
        ];
    }
}
