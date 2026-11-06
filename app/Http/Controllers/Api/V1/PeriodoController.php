<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPeriodoRequest;
use App\Http\Requests\ListarPeriodosRequest;
use App\Http\Resources\Api\V1\PeriodoResource;
use App\Models\Periodo;
use App\Models\Usuario;
use App\Services\GestionarEstructuraAcademica;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PeriodoController extends Controller
{
    public function index(ListarPeriodosRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Periodo::class);
        $filtros = $request->validated();
        $consulta = Periodo::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(
                isset($filtros['anio_escolar_id']),
                fn ($query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']),
            )
            ->when(isset($filtros['activo']), fn ($query) => $query->where('activo', $filtros['activo']))
            ->when(
                $filtros['buscar'] ?? null,
                fn ($query, string $buscar) => $query->where('nombre', 'like', '%'.$buscar.'%'),
            );
        $consulta = $this->ordenarLista($consulta, $filtros, [['anio_escolar_id', 'asc'], ['orden', 'asc'], ['id', 'asc']]);
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Periodos del año escolar.',
            'data' => [
                'periodos' => PeriodoResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function show(Periodo $periodo, Request $request): JsonResponse
    {
        Gate::authorize('view', $periodo);

        return response()->json([
            'message' => 'Periodo encontrado.',
            'data' => ['periodo' => (new PeriodoResource($periodo))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(GuardarPeriodoRequest $request, GestionarEstructuraAcademica $estructura): JsonResponse
    {
        $periodo = $estructura->crear($this->actor($request), new Periodo, $request->validated());

        return response()->json([
            'message' => 'Periodo creado.',
            'data' => ['periodo' => (new PeriodoResource($periodo))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function update(
        GuardarPeriodoRequest $request,
        Periodo $periodo,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        $actualizado = $estructura->actualizar($this->actor($request), $periodo, $request->validated());

        return response()->json([
            'message' => 'Periodo actualizado.',
            'data' => ['periodo' => (new PeriodoResource($actualizado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function destroy(
        Periodo $periodo,
        Request $request,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        Gate::authorize('delete', $periodo);
        $desactivado = $estructura->desactivar($this->actor($request), $periodo);

        return response()->json([
            'message' => 'Periodo desactivado.',
            'data' => ['periodo' => (new PeriodoResource($desactivado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    private function actor(Request $request): Usuario
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw new AuthenticationException;
        }

        return $usuario;
    }
}
