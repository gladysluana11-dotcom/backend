<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/** @mixin Permission */
class PermisoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $partes = explode('.', (string) $this->name);

        return [
            'nombre' => $this->name,
            'modulo' => array_shift($partes),
            'accion' => implode('.', $partes),
        ];
    }
}
