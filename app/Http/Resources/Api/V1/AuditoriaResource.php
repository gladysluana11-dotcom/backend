<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Auditoria */
class AuditoriaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'colegio_id' => $this->colegio_id,
            'usuario_id' => $this->usuario_id,
            'usuario' => $this->whenLoaded('usuario', fn (): ?array => $this->usuario === null
                ? null
                : [
                    'id' => $this->usuario->id,
                    'nombre' => $this->usuario->nombre,
                    'apellido' => $this->usuario->apellido,
                    'email' => $this->usuario->email,
                ]),
            'evento' => $this->evento,
            'entidad' => $this->entidad,
            'entidad_id' => $this->entidad_id,
            'valores_anteriores' => $this->valores_anteriores,
            'valores_nuevos' => $this->valores_nuevos,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'fecha' => $this->fecha,
        ];
    }
}
