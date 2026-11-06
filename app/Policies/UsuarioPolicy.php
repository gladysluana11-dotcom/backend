<?php

namespace App\Policies;

use App\Models\Usuario;

class UsuarioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->puedeGestionarUsuarios($usuario, 'usuarios.ver');
    }

    public function view(Usuario $actor, Usuario $usuario): bool
    {
        return $this->viewAny($actor) && $actor->colegio_id === $usuario->colegio_id;
    }

    public function create(Usuario $usuario): bool
    {
        return $this->puedeGestionarUsuarios($usuario, 'usuarios.crear');
    }

    public function update(Usuario $actor, Usuario $usuario): bool
    {
        return $this->puedeGestionarUsuarios($actor, 'usuarios.editar')
            && $actor->colegio_id === $usuario->colegio_id;
    }

    public function deactivate(Usuario $actor, Usuario $usuario): bool
    {
        return $this->puedeGestionarUsuarios($actor, 'usuarios.desactivar')
            && $actor->colegio_id === $usuario->colegio_id;
    }

    public function reactivate(Usuario $actor, Usuario $usuario): bool
    {
        return $this->puedeGestionarUsuarios($actor, 'usuarios.reactivar')
            && $actor->colegio_id === $usuario->colegio_id;
    }

    private function puedeGestionarUsuarios(Usuario $usuario, string $permiso): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador'])
            && $usuario->hasPermissionTo($permiso, 'web');
    }
}
