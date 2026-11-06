<?php

namespace App\Models\Concerns;

use App\Models\Usuario;
use App\Services\RegistrarAuditoria;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(static function (self $model): void {
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

        static::updated(static function (self $model): void {
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
            $evento = match (true) {
                ($cambios['estado'] ?? null) === 'retirado' => $model->entidadAuditable().'.retirado',
                array_key_exists('estado', $cambios) => $model->entidadAuditable().'.estado_actualizado',
                in_array($cambios['activo'] ?? null, [false, 0, '0'], true) => $model->entidadAuditable().'.desactivado',
                in_array($cambios['activo'] ?? null, [true, 1, '1'], true) => $model->entidadAuditable().'.reactivado',
                default => $model->entidadAuditable().'.actualizado',
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
