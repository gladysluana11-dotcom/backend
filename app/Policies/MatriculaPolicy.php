<?php

namespace App\Policies;

use App\Models\AsignacionSeccion;
use App\Models\Matricula;
use App\Models\Usuario;

class MatriculaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $this->permiteVer($usuario);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Usuario $usuario, Matricula $matricula): bool
    {
        if ($matricula->colegio_id !== $usuario->colegio_id || ! $this->permiteVer($usuario)) {
            return false;
        }

        if (! $usuario->hasAnyRole(['Docente', 'Auxiliar'])) {
            return true;
        }

        return AsignacionSeccion::query()
            ->where('colegio_id', $usuario->colegio_id)
            ->where('usuario_id', $usuario->id)
            ->where('seccion_id', $matricula->seccion_id)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Usuario $usuario): bool
    {
        return $this->permiteGestionar($usuario);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Usuario $usuario, Matricula $matricula): bool
    {
        return $matricula->colegio_id === $usuario->colegio_id
            && $this->permiteGestionar($usuario);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Usuario $usuario, Matricula $matricula): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Usuario $usuario, Matricula $matricula): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Usuario $usuario, Matricula $matricula): bool
    {
        return false;
    }

    public function manage(Usuario $usuario, Matricula $matricula): bool
    {
        return $this->update($usuario, $matricula);
    }

    public function authorizeOverCapacity(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasRole('Director')
            && $usuario->hasPermissionTo('matriculas.exceso_vacantes', 'web');
    }

    private function permiteVer(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole([
                'Director',
                'Administrador',
                'Tesorería',
                'Coordinación',
                'Docente',
                'Auxiliar',
            ])
            && $usuario->hasPermissionTo('matriculas.ver', 'web');
    }

    private function permiteGestionar(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador', 'Coordinación'])
            && $usuario->hasPermissionTo('matriculas.gestionar', 'web');
    }
}
