<?php

namespace App\Policies;

use App\Models\Cuota;
use App\Models\Usuario;

class CuotaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador', 'Tesorería'])
            && $usuario->hasPermissionTo('pagos.ver', 'web');
    }

    public function view(Usuario $usuario, Cuota $cuota): bool
    {
        return $cuota->colegio_id === $usuario->colegio_id && $this->viewAny($usuario);
    }
}
