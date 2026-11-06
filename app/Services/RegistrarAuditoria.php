<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;

class RegistrarAuditoria
{
    /**
     * @param  array<string, mixed>|null  $valoresAnteriores
     * @param  array<string, mixed>|null  $valoresNuevos
     */
    public function registrar(
        string $evento,
        string $entidad,
        ?int $entidadId,
        ?array $valoresAnteriores = null,
        ?array $valoresNuevos = null,
        ?Usuario $usuario = null,
        ?int $colegioId = null,
    ): Auditoria {
        if ($usuario === null) {
            $actor = Auth::user();
            $usuario = $actor instanceof Usuario ? $actor : null;
        }

        $request = app()->bound('request') ? request() : null;

        return Auditoria::query()->create([
            'colegio_id' => $colegioId ?? $usuario?->colegio_id,
            'usuario_id' => $usuario?->id,
            'evento' => $evento,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'valores_anteriores' => $this->filtrarValoresSensibles($valoresAnteriores),
            'valores_nuevos' => $this->filtrarValoresSensibles($valoresNuevos),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'fecha' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $valores
     * @return array<string, mixed>|null
     */
    private function filtrarValoresSensibles(?array $valores): ?array
    {
        if ($valores === null) {
            return null;
        }

        $camposProhibidos = [
            'password',
            'password_confirmation',
            'contrasena',
            'contrasena_actual',
            'contrasena_temporal',
            'token',
            'remember_token',
            'access_token',
        ];

        foreach ($valores as $campo => $valor) {
            if (in_array(mb_strtolower((string) $campo), $camposProhibidos, true)) {
                unset($valores[$campo]);
            } elseif (is_array($valor)) {
                $valores[$campo] = $this->filtrarValoresSensibles($valor);
            }
        }

        return $valores;
    }
}
