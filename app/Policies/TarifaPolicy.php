<?php

namespace App\Policies;

use App\Models\Tarifa;
use App\Models\Usuario;

class TarifaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador', 'Tesorería'])
            && $usuario->hasPermissionTo('tarifas.ver', 'web');
    }

    public function view(Usuario $usuario, Tarifa $tarifa): bool
    {
        return $tarifa->colegio_id === $usuario->colegio_id && $this->viewAny($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->puedeGestionar($usuario);
    }

    public function update(Usuario $usuario, Tarifa $tarifa): bool
    {
        return $tarifa->colegio_id === $usuario->colegio_id && $this->puedeGestionar($usuario);
    }

    public function delete(Usuario $usuario, Tarifa $tarifa): bool
    {
        return $this->update($usuario, $tarifa);
    }

    public function restore(Usuario $usuario, Tarifa $tarifa): bool
    {
        return false;
    }

    public function forceDelete(Usuario $usuario, Tarifa $tarifa): bool
    {
        return false;
    }

    private function puedeGestionar(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador'])
            && $usuario->hasPermissionTo('tarifas.gestionar', 'web');
    }
}
