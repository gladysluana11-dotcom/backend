<?php

namespace App\Models\Concerns;

use App\Models\Usuario;
use App\Services\RegistrarAuditoria;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(static function (Usuario $model): void {
            $usuario = Auth::user();

            if (! $usuario instanceof Usuario) {
                return;
            }

            app(RegistrarAuditoria::class)->registrar(
                $model->eventoAuditableCreado(),
                $model->entidadAuditable(),
                (int) $model->getKey(),
                valoresNuevos: $model->only($model->atributosAuditables()),
                usuario: $usuario,
                colegioId: $model->getAttribute('colegio_id') === null
                    ? null
                    : (int) $model->getAttribute('colegio_id'),
            );
        });

        static::updated(static function (Usuario $model): void {
            $usuario = Auth::user();

            if (! $usuario instanceof Usuario) {
                return;
            }

            $camposAuditables = $model->atributosAuditables();
            $cambios = array_intersect_key($model->getChanges(), array_flip($camposAuditables));

            if ($cambios === []) {
                return;
            }

            $anteriores = array_intersect_key($model->getOriginal(), $cambios);
            $evento = match ($cambios['activo'] ?? null) {
                false, 0, '0' => 'usuarios.desactivado',
                true, 1, '1' => 'usuarios.reactivado',
                default => 'usuarios.actualizado',
            };

            app(RegistrarAuditoria::class)->registrar(
                $evento,
                $model->entidadAuditable(),
                (int) $model->getKey(),
                valoresAnteriores: $anteriores,
                valoresNuevos: $cambios,
                usuario: $usuario,
                colegioId: $model->getAttribute('colegio_id') === null
                    ? null
                    : (int) $model->getAttribute('colegio_id'),
            );
        });
    }

    /**
     * @return list<string>
     */
    abstract protected function atributosAuditables(): array;

    abstract protected function eventoAuditableCreado(): string;

    abstract protected function entidadAuditable(): string;
}
