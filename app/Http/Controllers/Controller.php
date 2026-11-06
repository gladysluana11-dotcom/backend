<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $consulta
     * @param  array<string, mixed>  $filtros
     * @param  list<array{0: string, 1: 'asc'|'desc'}>  $ordenPredeterminado
     * @return Builder<TModel>
     */
    protected function ordenarLista(Builder $consulta, array $filtros, array $ordenPredeterminado): Builder
    {
        if (isset($filtros['sort'])) {
            $consulta->orderBy($filtros['sort'], $filtros['direction'] ?? 'asc');

            if ($filtros['sort'] !== 'id') {
                $consulta->orderBy('id');
            }

            return $consulta;
        }

        foreach ($ordenPredeterminado as [$columna, $direccion]) {
            $consulta->orderBy($columna, $direccion);
        }

        return $consulta;
    }

    /**
     * @return array{pagina: int, por_pagina: int, total: int, ultima_pagina: int, siguiente: ?string, anterior: ?string}
     */
    protected function datosPaginacion(LengthAwarePaginator $paginador): array
    {
        return [
            'pagina' => $paginador->currentPage(),
            'por_pagina' => $paginador->perPage(),
            'total' => $paginador->total(),
            'ultima_pagina' => $paginador->lastPage(),
            'siguiente' => $paginador->nextPageUrl(),
            'anterior' => $paginador->previousPageUrl(),
        ];
    }
}
