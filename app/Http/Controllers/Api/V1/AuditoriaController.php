<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListarAuditoriasRequest;
use App\Http\Resources\Api\V1\AuditoriaResource;
use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;

class AuditoriaController extends Controller
{
    public function index(ListarAuditoriasRequest $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof Usuario && $usuario->colegio_id !== null, 403);

        $filtros = $request->validated();
        $paginador = Auditoria::query()
            ->where('colegio_id', $usuario->colegio_id)
            ->with([
                'usuario' => static function (BelongsTo $consulta) use ($usuario): void {
                    $consulta->select(['id', 'colegio_id', 'nombre', 'apellido', 'email'])
                        ->where('colegio_id', $usuario->colegio_id);
                },
            ])
            ->when($filtros['usuario_id'] ?? null, fn ($consulta, int $usuarioId) => $consulta->where('usuario_id', $usuarioId))
            ->when($filtros['evento'] ?? null, fn ($consulta, string $evento) => $consulta->where('evento', $evento))
            ->when($filtros['entidad'] ?? null, fn ($consulta, string $entidad) => $consulta->where('entidad', $entidad))
            ->when($filtros['fecha_desde'] ?? null, fn ($consulta, string $fecha) => $consulta->whereDate('fecha', '>=', $fecha))
            ->when($filtros['fecha_hasta'] ?? null, fn ($consulta, string $fecha) => $consulta->whereDate('fecha', '<=', $fecha))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Auditoría del colegio.',
            'data' => [
                'auditorias' => AuditoriaResource::collection($paginador)->resolve($request),
                'paginacion' => [
                    'pagina' => $paginador->currentPage(),
                    'por_pagina' => $paginador->perPage(),
                    'total' => $paginador->total(),
                    'ultima_pagina' => $paginador->lastPage(),
                    'siguiente' => $paginador->nextPageUrl(),
                    'anterior' => $paginador->previousPageUrl(),
                ],
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }
}
