<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarNivelRequest;
use App\Http\Requests\ListarNivelesRequest;
use App\Http\Resources\Api\V1\NivelResource;
use App\Models\Nivel;
use App\Models\Usuario;
use App\Services\GestionarEstructuraAcademica;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NivelController extends Controller
{
    public function index(ListarNivelesRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Nivel::class);
        $filtros = $request->validated();
        $consulta = Nivel::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(isset($filtros['activo']), fn ($query) => $query->where('activo', $filtros['activo']))
            ->when(
                $filtros['buscar'] ?? null,
                fn ($query, string $buscar) => $query->where('nombre', 'like', '%'.$buscar.'%'),
            );
        $consulta = $this->ordenarLista($consulta, $filtros, [['orden', 'asc'], ['id', 'asc']]);
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Niveles del colegio.',
            'data' => [
                'niveles' => NivelResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function show(Nivel $nivel, Request $request): JsonResponse
    {
        Gate::authorize('view', $nivel);

        return response()->json([
            'message' => 'Nivel encontrado.',
            'data' => ['nivel' => (new NivelResource($nivel))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(GuardarNivelRequest $request, GestionarEstructuraAcademica $estructura): JsonResponse
    {
        $nivel = $estructura->crear($this->actor($request), new Nivel, $request->validated());

        return response()->json([
            'message' => 'Nivel creado.',
            'data' => ['nivel' => (new NivelResource($nivel))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function update(
        GuardarNivelRequest $request,
        Nivel $nivel,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        $actualizado = $estructura->actualizar($this->actor($request), $nivel, $request->validated());

        return response()->json([
            'message' => 'Nivel actualizado.',
            'data' => ['nivel' => (new NivelResource($actualizado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function destroy(
        Nivel $nivel,
        Request $request,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        Gate::authorize('delete', $nivel);
        $desactivado = $estructura->desactivar($this->actor($request), $nivel);

        return response()->json([
            'message' => 'Nivel desactivado.',
            'data' => ['nivel' => (new NivelResource($desactivado))->resolve($request)],
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
