<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarSeccionRequest;
use App\Http\Requests\ListarSeccionesRequest;
use App\Http\Resources\Api\V1\SeccionResource;
use App\Models\Seccion;
use App\Models\Usuario;
use App\Services\GestionarEstructuraAcademica;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SeccionController extends Controller
{
    public function index(ListarSeccionesRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Seccion::class);
        $filtros = $request->validated();
        $consulta = Seccion::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(
                isset($filtros['anio_escolar_id']),
                fn ($query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']),
            )
            ->when(isset($filtros['grado_id']), fn ($query) => $query->where('grado_id', $filtros['grado_id']))
            ->when(isset($filtros['activo']), fn ($query) => $query->where('activo', $filtros['activo']))
            ->when(
                $filtros['buscar'] ?? null,
                fn ($query, string $buscar) => $query->where('nombre', 'like', '%'.$buscar.'%'),
            );
        $consulta = $this->ordenarLista($consulta, $filtros, [['grado_id', 'asc'], ['nombre', 'asc'], ['id', 'asc']]);
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Secciones del colegio.',
            'data' => [
                'secciones' => SeccionResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function show(Seccion $seccion, Request $request): JsonResponse
    {
        Gate::authorize('view', $seccion);

        return response()->json([
            'message' => 'Sección encontrada.',
            'data' => ['seccion' => (new SeccionResource($seccion))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(GuardarSeccionRequest $request, GestionarEstructuraAcademica $estructura): JsonResponse
    {
        $seccion = $estructura->crear($this->actor($request), new Seccion, $request->validated());

        return response()->json([
            'message' => 'Sección creada.',
            'data' => ['seccion' => (new SeccionResource($seccion))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function update(
        GuardarSeccionRequest $request,
        Seccion $seccion,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        $actualizada = $estructura->actualizar($this->actor($request), $seccion, $request->validated());

        return response()->json([
            'message' => 'Sección actualizada.',
            'data' => ['seccion' => (new SeccionResource($actualizada))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function destroy(
        Seccion $seccion,
        Request $request,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        Gate::authorize('delete', $seccion);
        $desactivada = $estructura->desactivar($this->actor($request), $seccion);

        return response()->json([
            'message' => 'Sección desactivada.',
            'data' => ['seccion' => (new SeccionResource($desactivada))->resolve($request)],
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
