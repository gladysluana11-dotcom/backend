<?php

namespace App\Policies;

use App\Models\Cuota;
use App\Models\Descuento;
use App\Models\Matricula;
use App\Models\Usuario;

class DescuentoPolicy
{
    public function applyTo(Usuario $usuario, Cuota $cuota): bool
    {
        return $cuota->colegio_id === $usuario->colegio_id && $this->puedeAprobar($usuario);
    }

    public function applyToMatricula(Usuario $usuario, Matricula $matricula): bool
    {
        return $matricula->colegio_id === $usuario->colegio_id && $this->puedeAprobar($usuario);
    }

    public function annul(Usuario $usuario, Descuento $descuento): bool
    {
        return $descuento->colegio_id === $usuario->colegio_id && $this->puedeAprobar($usuario);
    }

    private function puedeAprobar(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasRole('Director')
            && $usuario->hasPermissionTo('descuentos.aprobar', 'web');
    }
}
