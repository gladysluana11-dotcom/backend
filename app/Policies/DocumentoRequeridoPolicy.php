<?php

namespace App\Policies;

use App\Models\DocumentoRequerido;
use App\Models\Usuario;

class DocumentoRequeridoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $this->permite($usuario, 'matriculas.ver');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Usuario $usuario, DocumentoRequerido $documentoRequerido): bool
    {
        return $documentoRequerido->colegio_id === $usuario->colegio_id
            && $this->viewAny($usuario);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Usuario $usuario): bool
    {
        return $this->permiteGestion($usuario);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Usuario $usuario, DocumentoRequerido $documentoRequerido): bool
    {
        return $documentoRequerido->colegio_id === $usuario->colegio_id
            && $this->permiteGestion($usuario);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Usuario $usuario, DocumentoRequerido $documentoRequerido): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Usuario $usuario, DocumentoRequerido $documentoRequerido): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Usuario $usuario, DocumentoRequerido $documentoRequerido): bool
    {
        return false;
    }

    private function permiteGestion(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador'])
            && $usuario->hasPermissionTo('matriculas.gestionar', 'web');
    }

    private function permite(Usuario $usuario, string $permiso): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole([
                'Director',
                'Administrador',
                'Coordinación',
                'Docente',
                'Auxiliar',
                'Tesorería',
            ])
            && $usuario->hasPermissionTo($permiso, 'web');
    }
}
