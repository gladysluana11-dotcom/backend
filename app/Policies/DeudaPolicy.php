<?php

namespace App\Policies;

use App\Models\Usuario;

class DeudaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasPermissionTo('deudas.ver', 'web')
            && $usuario->hasAnyRole(['Director', 'Administrador', 'Tesorería', 'Coordinación']);
    }
}
