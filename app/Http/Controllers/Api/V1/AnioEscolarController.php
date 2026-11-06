<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarAnioEscolarRequest;
use App\Http\Requests\ListarAniosEscolaresRequest;
use App\Http\Resources\Api\V1\AnioEscolarResource;
use App\Models\AnioEscolar;
use App\Models\Usuario;
use App\Services\GestionarAniosEscolares;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnioEscolarController extends Controller
{
    public function index(ListarAniosEscolaresRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', AnioEscolar::class);
        $filtros = $request->validated();
        $consulta = AnioEscolar::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(isset($filtros['activo']), fn ($query) => $query->where('activo', $filtros['activo']))
            ->when(isset($filtros['cerrado']), fn ($query) => $query->where('cerrado', $filtros['cerrado']))
            ->when(
                $filtros['buscar'] ?? null,
                fn ($query, string $buscar) => $query->where('nombre', 'like', '%'.$buscar.'%'),
            );
        $consulta = $this->ordenarLista($consulta, $filtros, [['nombre', 'desc'], ['id', 'desc']]);
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Años escolares del colegio.',
            'data' => [
                'anios_escolares' => AnioEscolarResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function show(AnioEscolar $anio_escolar, Request $request): JsonResponse
    {
        Gate::authorize('view', $anio_escolar);

        return response()->json([
            'message' => 'Año escolar encontrado.',
            'data' => ['anio_escolar' => (new AnioEscolarResource($anio_escolar))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(GuardarAnioEscolarRequest $request, GestionarAniosEscolares $anios): JsonResponse
    {
        $anio = $anios->crear($this->actor($request), $request->validated());

        return response()->json([
            'message' => 'Año escolar creado.',
            'data' => ['anio_escolar' => (new AnioEscolarResource($anio))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function update(
        GuardarAnioEscolarRequest $request,
        AnioEscolar $anio_escolar,
        GestionarAniosEscolares $anios,
    ): JsonResponse {
        $anio = $anios->actualizar($this->actor($request), $anio_escolar, $request->validated());

        return response()->json([
            'message' => 'Año escolar actualizado.',
            'data' => ['anio_escolar' => (new AnioEscolarResource($anio))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function abrir(
        AnioEscolar $anio_escolar,
        Request $request,
        GestionarAniosEscolares $anios,
    ): JsonResponse {
        Gate::authorize('open', $anio_escolar);
        $anio = $anios->abrir($this->actor($request), $anio_escolar);

        return response()->json([
            'message' => 'Año escolar abierto.',
            'data' => ['anio_escolar' => (new AnioEscolarResource($anio))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function cerrar(
        AnioEscolar $anio_escolar,
        Request $request,
        GestionarAniosEscolares $anios,
    ): JsonResponse {
        Gate::authorize('close', $anio_escolar);
        $anio = $anios->cerrar($this->actor($request), $anio_escolar);

        return response()->json([
            'message' => 'Año escolar cerrado.',
            'data' => ['anio_escolar' => (new AnioEscolarResource($anio))->resolve($request)],
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
