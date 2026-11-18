<?php

namespace App\Policies;

use App\Models\Usuario;

class ReportePolicy
{
    public function view(Usuario $usuario, string $reporte): bool
    {
        if ($usuario->colegio_id === null || ! $usuario->hasPermissionTo('reportes.ver', 'web')) {
            return false;
        }

        if (in_array($reporte, ['matriculas', 'deudas_aula'], true)) {
            return $usuario->hasAnyRole([
                'Director',
                'Administrador',
                'Tesorería',
                'Coordinación',
                'Superadministrador',
            ]);
        }

        return $usuario->hasAnyRole(['Director', 'Administrador', 'Tesorería', 'Superadministrador']);
    }
}
