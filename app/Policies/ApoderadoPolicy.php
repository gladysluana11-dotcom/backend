<?php

namespace App\Policies;

use App\Models\Apoderado;
use App\Models\Usuario;

class ApoderadoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $this->permite($usuario, 'apoderados.ver');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Usuario $usuario, Apoderado $apoderado): bool
    {
        if ($apoderado->colegio_id !== $usuario->colegio_id || ! $this->viewAny($usuario)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Usuario $usuario): bool
    {
        return $this->permiteGestion($usuario, 'apoderados.crear');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Usuario $usuario, Apoderado $apoderado): bool
    {
        return $apoderado->colegio_id === $usuario->colegio_id
            && $this->permiteGestion($usuario, 'apoderados.editar');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Usuario $usuario, Apoderado $apoderado): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Usuario $usuario, Apoderado $apoderado): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Usuario $usuario, Apoderado $apoderado): bool
    {
        return false;
    }

    private function permiteGestion(Usuario $usuario, string $permiso): bool
    {
        return $usuario->hasAnyRole(['Director', 'Administrador', 'Coordinación'])
            && $this->permite($usuario, $permiso);
    }

    private function permite(Usuario $usuario, string $permiso): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole([
                'Director',
                'Administrador',
                'Tesorería',
                'Coordinación',
            ])
            && $usuario->hasPermissionTo($permiso, 'web');
    }
}
