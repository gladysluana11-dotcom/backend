<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarGradoRequest;
use App\Http\Requests\ListarGradosRequest;
use App\Http\Resources\Api\V1\GradoResource;
use App\Models\Grado;
use App\Models\Usuario;
use App\Services\GestionarEstructuraAcademica;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GradoController extends Controller
{
    public function index(ListarGradosRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Grado::class);
        $filtros = $request->validated();
        $consulta = Grado::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(isset($filtros['nivel_id']), fn ($query) => $query->where('nivel_id', $filtros['nivel_id']))
            ->when(isset($filtros['activo']), fn ($query) => $query->where('activo', $filtros['activo']))
            ->when(
                $filtros['buscar'] ?? null,
                fn ($query, string $buscar) => $query->where('nombre', 'like', '%'.$buscar.'%'),
            );
        $consulta = $this->ordenarLista($consulta, $filtros, [['nivel_id', 'asc'], ['orden', 'asc'], ['id', 'asc']]);
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Grados del colegio.',
            'data' => [
                'grados' => GradoResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function show(Grado $grado, Request $request): JsonResponse
    {
        Gate::authorize('view', $grado);

        return response()->json([
            'message' => 'Grado encontrado.',
            'data' => ['grado' => (new GradoResource($grado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(GuardarGradoRequest $request, GestionarEstructuraAcademica $estructura): JsonResponse
    {
        $grado = $estructura->crear($this->actor($request), new Grado, $request->validated());

        return response()->json([
            'message' => 'Grado creado.',
            'data' => ['grado' => (new GradoResource($grado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function update(
        GuardarGradoRequest $request,
        Grado $grado,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        $actualizado = $estructura->actualizar($this->actor($request), $grado, $request->validated());

        return response()->json([
            'message' => 'Grado actualizado.',
            'data' => ['grado' => (new GradoResource($actualizado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function destroy(
        Grado $grado,
        Request $request,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        Gate::authorize('delete', $grado);
        $desactivado = $estructura->desactivar($this->actor($request), $grado);

        return response()->json([
            'message' => 'Grado desactivado.',
            'data' => ['grado' => (new GradoResource($desactivado))->resolve($request)],
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
