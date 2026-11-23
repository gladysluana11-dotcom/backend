<?php

namespace App\Http\Resources\Api\V1;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

#[SchemaName('Reporte')]
class ReporteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $datos = parent::toArray($request);
        $datos['filtros'] = (object) ($datos['filtros'] ?? []);

        return $datos;
    }
}
