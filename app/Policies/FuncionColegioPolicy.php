<?php

namespace App\Policies;

use App\Models\FuncionColegio;
use App\Models\Usuario;

class FuncionColegioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->puedeGestionar($usuario, 'funciones.ver');
    }

    public function update(
        Usuario $usuario,
        string|FuncionColegio|null $funciones = null,
    ): bool {
        return $this->puedeGestionar($usuario, 'funciones.editar')
            && (! $funciones instanceof FuncionColegio || $funciones->colegio_id === $usuario->colegio_id);
    }

    private function puedeGestionar(Usuario $usuario, string $permiso): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador'])
            && $usuario->hasPermissionTo($permiso, 'web');
    }
}
