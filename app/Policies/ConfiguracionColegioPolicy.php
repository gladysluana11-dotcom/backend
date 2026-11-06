<?php

namespace App\Policies;

use App\Models\ConfiguracionColegio;
use App\Models\Usuario;

class ConfiguracionColegioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador', 'Tesorería', 'Coordinación'])
            && $usuario->hasPermissionTo('configuracion.ver', 'web');
    }

    public function update(
        Usuario $usuario,
        string|ConfiguracionColegio|null $configuracion = null,
    ): bool {
        return $usuario->colegio_id !== null
            && $usuario->hasAnyRole(['Director', 'Administrador'])
            && $usuario->hasPermissionTo('configuracion.editar', 'web')
            && (! $configuracion instanceof ConfiguracionColegio
                || $configuracion->colegio_id === $usuario->colegio_id);
    }
}
