<?php

namespace App\Policies;

use App\Models\Usuario;

class GestionCobranzaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->puedeGestionar($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->puedeGestionar($usuario);
    }

    private function puedeGestionar(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasPermissionTo('cobranza.gestionar', 'web')
            && $usuario->hasAnyRole(['Director', 'Tesorería', 'Coordinación']);
    }
}
