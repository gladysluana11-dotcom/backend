<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
class UsuarioResource extends JsonResource
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
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'email' => $this->email,
            'activo' => $this->activo,
            'requiere_cambio_contrasena' => $this->requiere_cambio_contrasena,
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->pluck('name')->values()->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
