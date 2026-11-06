<?php

namespace App\Models\Concerns;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LogicException;

trait BelongsToColegio
{
    protected static function bootBelongsToColegio(): void
    {
        static::addGlobalScope('colegio', function (Builder $builder): void {
            $usuario = Auth::user();

            if ($usuario instanceof Usuario && $usuario->colegio_id !== null) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('colegio_id'),
                    $usuario->colegio_id,
                );
            }
        });

        static::creating(function (Model $model): void {
            $usuario = Auth::user();

            if (! $usuario instanceof Usuario) {
                return;
            }

            if ($usuario->colegio_id === null) {
                throw new LogicException('El superadministrador debe seleccionar un colegio para crear datos.');
            }

            $model->setAttribute('colegio_id', $usuario->colegio_id);
        });
    }
}
