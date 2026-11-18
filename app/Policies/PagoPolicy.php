<?php

namespace App\Policies;

use App\Models\Pago;
use App\Models\Usuario;

class PagoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->tienePermiso($usuario, 'pagos.ver')
            && $usuario->hasAnyRole(['Director', 'Administrador', 'Tesorería']);
    }

    public function view(Usuario $usuario, Pago $pago): bool
    {
        return $pago->colegio_id === $usuario->colegio_id && $this->viewAny($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->tienePermiso($usuario, 'pagos.registrar')
            && $usuario->hasAnyRole(['Director', 'Tesorería']);
    }

    public function annul(Usuario $usuario, Pago $pago): bool
    {
        return $pago->colegio_id === $usuario->colegio_id
            && $this->tienePermiso($usuario, 'pagos.anular')
            && $usuario->hasAnyRole(['Director', 'Tesorería']);
    }

    public function manageVoucher(Usuario $usuario, Pago $pago): bool
    {
        return $pago->colegio_id === $usuario->colegio_id
            && $this->tienePermiso($usuario, 'pagos.registrar')
            && $usuario->hasAnyRole(['Director', 'Tesorería']);
    }

    private function tienePermiso(Usuario $usuario, string $permiso): bool
    {
        return $usuario->colegio_id !== null && $usuario->hasPermissionTo($permiso, 'web');
    }
}
