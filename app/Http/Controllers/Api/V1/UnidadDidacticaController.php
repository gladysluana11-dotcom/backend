<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarUnidadDidacticaRequest;
use App\Http\Requests\ListarUnidadesDidacticasRequest;
use App\Http\Resources\Api\V1\UnidadDidacticaResource;
use App\Models\UnidadDidactica;
use App\Models\Usuario;
use App\Services\GestionarEstructuraAcademica;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UnidadDidacticaController extends Controller
{
    public function index(ListarUnidadesDidacticasRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', UnidadDidactica::class);
        $filtros = $request->validated();
        $consulta = UnidadDidactica::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(
                isset($filtros['periodo_id']),
                fn ($query) => $query->where('periodo_id', $filtros['periodo_id']),
            )
            ->when(isset($filtros['activo']), fn ($query) => $query->where('activo', $filtros['activo']))
            ->when(
                $filtros['buscar'] ?? null,
                fn ($query, string $buscar) => $query->where('nombre', 'like', '%'.$buscar.'%'),
            );
        $consulta = $this->ordenarLista($consulta, $filtros, [['periodo_id', 'asc'], ['orden', 'asc'], ['id', 'asc']]);
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Unidades didácticas del periodo.',
            'data' => [
                'unidades_didacticas' => UnidadDidacticaResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function show(UnidadDidactica $unidad_didactica, Request $request): JsonResponse
    {
        Gate::authorize('view', $unidad_didactica);

        return response()->json([
            'message' => 'Unidad didáctica encontrada.',
            'data' => [
                'unidad_didactica' => (new UnidadDidacticaResource($unidad_didactica))->resolve($request),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(
        GuardarUnidadDidacticaRequest $request,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        $unidad = $estructura->crear($this->actor($request), new UnidadDidactica, $request->validated());

        return response()->json([
            'message' => 'Unidad didáctica creada.',
            'data' => ['unidad_didactica' => (new UnidadDidacticaResource($unidad))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function update(
        GuardarUnidadDidacticaRequest $request,
        UnidadDidactica $unidad_didactica,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        $actualizada = $estructura->actualizar(
            $this->actor($request),
            $unidad_didactica,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Unidad didáctica actualizada.',
            'data' => ['unidad_didactica' => (new UnidadDidacticaResource($actualizada))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function destroy(
        UnidadDidactica $unidad_didactica,
        Request $request,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        Gate::authorize('delete', $unidad_didactica);
        $desactivada = $estructura->desactivar($this->actor($request), $unidad_didactica);

        return response()->json([
            'message' => 'Unidad didáctica desactivada.',
            'data' => ['unidad_didactica' => (new UnidadDidacticaResource($desactivada))->resolve($request)],
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
