<?php

namespace App\Policies;

use App\Models\Alumno;
use App\Models\AsignacionSeccion;
use App\Models\Usuario;

class AlumnoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $this->permite($usuario, 'alumnos.ver');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Usuario $usuario, Alumno $alumno): bool
    {
        if ($alumno->colegio_id !== $usuario->colegio_id || ! $this->viewAny($usuario)) {
            return false;
        }

        if (! $usuario->hasAnyRole(['Docente', 'Auxiliar'])) {
            return true;
        }

        return $alumno->matriculas()
            ->whereIn('seccion_id', $this->seccionesAsignadas($usuario))
            ->whereIn('estado', ['pre_inscrito', 'pendiente_documentos', 'matriculado'])
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Usuario $usuario): bool
    {
        return $this->permiteGestion($usuario, 'alumnos.crear');
    }

    public function import(Usuario $usuario): bool
    {
        return $this->permiteGestion($usuario, 'alumnos.importar');
    }

    public function historial(Usuario $usuario, Alumno $alumno): bool
    {
        return $this->view($usuario, $alumno);
    }

    public function photo(Usuario $usuario, Alumno $alumno): bool
    {
        return $this->view($usuario, $alumno);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Usuario $usuario, Alumno $alumno): bool
    {
        return $alumno->colegio_id === $usuario->colegio_id
            && $this->permiteGestion($usuario, 'alumnos.editar');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Usuario $usuario, Alumno $alumno): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Usuario $usuario, Alumno $alumno): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Usuario $usuario, Alumno $alumno): bool
    {
        return false;
    }

    /**
     * @return list<int>
     */
    public function seccionesAsignadas(Usuario $usuario): array
    {
        return AsignacionSeccion::query()
            ->where('colegio_id', $usuario->colegio_id)
            ->where('usuario_id', $usuario->id)
            ->where('activo', true)
            ->pluck('seccion_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
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
                'Docente',
                'Auxiliar',
            ])
            && $usuario->hasPermissionTo($permiso, 'web');
    }
}
