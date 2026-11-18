<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $alumno_id
 * @property-read string $nombre
 * @property-read string $apellido
 * @property-read string|null $nivel
 * @property-read string|null $grado
 * @property-read string|null $seccion
 * @property-read string $total_vencido
 * @property-read string $total_pendiente
 * @property-read int $cuotas_vencidas
 * @property-read int $dias_atraso
 * @property-read array{nombre:string,apellido:string,telefono:string|null,email:string|null}|null $apoderado_responsable_pago
 */
class DeudaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'alumno_id' => $this['alumno_id'],
            'nombre' => $this['nombre'],
            'apellido' => $this['apellido'],
            'nivel' => $this['nivel'],
            'grado' => $this['grado'],
            'seccion' => $this['seccion'],
            'total_vencido' => $this['total_vencido'],
            'total_pendiente' => $this['total_pendiente'],
            'cuotas_vencidas' => $this['cuotas_vencidas'],
            'dias_atraso' => $this['dias_atraso'],
            'apoderado_responsable_pago' => $this['apoderado_responsable_pago'],
        ];
    }
}
