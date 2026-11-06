<?php

namespace App\Policies;

use App\Models\Auditoria;
use App\Models\Usuario;

class AuditoriaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador'])
            && $usuario->hasPermissionTo('auditoria.ver', 'web');
    }

    public function view(Usuario $usuario, Auditoria $auditoria): bool
    {
        return $this->viewAny($usuario) && $usuario->colegio_id === $auditoria->colegio_id;
    }
}
