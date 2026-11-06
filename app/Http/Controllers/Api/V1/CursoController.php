<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarCursoRequest;
use App\Http\Requests\ListarCursosRequest;
use App\Http\Resources\Api\V1\CursoResource;
use App\Models\Curso;
use App\Models\Usuario;
use App\Services\GestionarEstructuraAcademica;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CursoController extends Controller
{
    public function index(ListarCursosRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Curso::class);
        $filtros = $request->validated();
        $consulta = Curso::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(isset($filtros['nivel_id']), fn ($query) => $query->where('nivel_id', $filtros['nivel_id']))
            ->when(isset($filtros['grado_id']), fn ($query) => $query->where('grado_id', $filtros['grado_id']))
            ->when(isset($filtros['activo']), fn ($query) => $query->where('activo', $filtros['activo']))
            ->when(
                $filtros['buscar'] ?? null,
                fn ($query, string $buscar) => $query->where('nombre', 'like', '%'.$buscar.'%'),
            );
        $consulta = $this->ordenarLista(
            $consulta,
            $filtros,
            [['nivel_id', 'asc'], ['grado_id', 'asc'], ['nombre', 'asc'], ['id', 'asc']],
        );
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Cursos del colegio.',
            'data' => [
                'cursos' => CursoResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function show(Curso $curso, Request $request): JsonResponse
    {
        Gate::authorize('view', $curso);

        return response()->json([
            'message' => 'Curso encontrado.',
            'data' => ['curso' => (new CursoResource($curso))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(GuardarCursoRequest $request, GestionarEstructuraAcademica $estructura): JsonResponse
    {
        $curso = $estructura->crear($this->actor($request), new Curso, $request->validated());

        return response()->json([
            'message' => 'Curso creado.',
            'data' => ['curso' => (new CursoResource($curso))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function update(
        GuardarCursoRequest $request,
        Curso $curso,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        $actualizado = $estructura->actualizar($this->actor($request), $curso, $request->validated());

        return response()->json([
            'message' => 'Curso actualizado.',
            'data' => ['curso' => (new CursoResource($actualizado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function destroy(
        Curso $curso,
        Request $request,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        Gate::authorize('delete', $curso);
        $desactivado = $estructura->desactivar($this->actor($request), $curso);

        return response()->json([
            'message' => 'Curso desactivado.',
            'data' => ['curso' => (new CursoResource($desactivado))->resolve($request)],
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
