<?php

namespace App\Policies;

use App\Models\CierreCaja;
use App\Models\Usuario;

class CierreCajaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasPermissionTo('pagos.ver', 'web')
            && $usuario->hasAnyRole(['Director', 'Tesorería']);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasPermissionTo('caja.cerrar', 'web')
            && $usuario->hasAnyRole(['Director', 'Tesorería']);
    }

    public function view(Usuario $usuario, CierreCaja $cierreCaja): bool
    {
        return $cierreCaja->colegio_id === $usuario->colegio_id && $this->viewAny($usuario);
    }
}
