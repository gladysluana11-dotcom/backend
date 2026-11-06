<?php

namespace App\Policies;

use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class AcademicoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->permite($usuario, 'academico.ver');
    }

    public function view(Usuario $usuario, Model $registro): bool
    {
        if (
            ! $this->permite($usuario, 'academico.ver')
            || $registro->getAttribute('colegio_id') !== $usuario->colegio_id
        ) {
            return false;
        }

        if ($registro instanceof AsignacionDocente) {
            return $this->puedeVerTodasLasAsignaciones($usuario)
                || (
                    $registro->docente_id === $usuario->id
                    && $this->viewOwnAssignments($usuario)
                );
        }

        return true;
    }

    public function viewAssignments(Usuario $usuario): bool
    {
        return $this->puedeVerTodasLasAsignaciones($usuario);
    }

    public function viewOwnAssignments(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasRole('Docente')
            && $usuario->hasPermissionTo('academico.asignaciones.propias', 'web');
    }

    public function create(Usuario $usuario): bool
    {
        return $this->permite($usuario, 'academico.crear');
    }

    public function createAssignment(Usuario $usuario): bool
    {
        return $this->permite($usuario, 'academico.editar');
    }

    public function import(Usuario $usuario): bool
    {
        return $this->permite($usuario, 'academico.importar');
    }

    public function update(Usuario $usuario, string|Model $registro): bool
    {
        if ($registro instanceof Model && $registro->getAttribute('colegio_id') !== $usuario->colegio_id) {
            return false;
        }

        return $this->permite($usuario, 'academico.editar');
    }

    public function delete(Usuario $usuario, Model $registro): bool
    {
        return $registro->getAttribute('colegio_id') === $usuario->colegio_id
            && $this->permite($usuario, 'academico.desactivar');
    }

    public function open(Usuario $usuario, AnioEscolar $anio): bool
    {
        return $anio->colegio_id === $usuario->colegio_id
            && $this->permite($usuario, 'academico.editar');
    }

    public function close(Usuario $usuario, AnioEscolar $anio): bool
    {
        return $this->open($usuario, $anio);
    }

    private function permite(Usuario $usuario, string $permiso): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador', 'Coordinación', 'Docente', 'Auxiliar'])
            && $usuario->hasPermissionTo($permiso, 'web');
    }

    private function puedeVerTodasLasAsignaciones(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador', 'Coordinación'])
            && $usuario->hasPermissionTo('academico.ver', 'web');
    }
}
