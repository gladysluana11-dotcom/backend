<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarTarifaRequest;
use App\Http\Requests\ListarTarifasRequest;
use App\Http\Resources\Api\V1\TarifaResource;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Services\GestionarTarifas;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TarifaController extends Controller
{
    #[Response(
        status: 200,
        type: 'array{message:string,data:array{tarifas:list<TarifaResource>,paginacion:array{pagina:int,por_pagina:int,total:int,ultima_pagina:int,siguiente:string|null,anterior:string|null}},errors:null,code:string}',
    )]
    public function index(ListarTarifasRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $consulta = Tarifa::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(isset($filtros['anio_escolar_id']), fn ($query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']))
            ->when(isset($filtros['grado_id']), fn ($query) => $query->where('grado_id', $filtros['grado_id']))
            ->with(['anioEscolar:id,nombre', 'grado:id,nombre']);
        $consulta = $this->ordenarLista(
            $consulta,
            $filtros,
            [['anio_escolar_id', 'desc'], ['grado_id', 'asc'], ['id', 'asc']],
        );
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Tarifas del colegio.',
            'data' => [
                'tarifas' => TarifaResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[Response(status: 200, type: 'array{message:string,data:array{tarifa:TarifaResource},errors:null,code:string}')]
    public function show(Tarifa $tarifa, Request $request): JsonResponse
    {
        Gate::authorize('view', $tarifa);
        $tarifa->load(['anioEscolar:id,nombre', 'grado:id,nombre']);

        return response()->json([
            'message' => 'Tarifa encontrada.',
            'data' => ['tarifa' => (new TarifaResource($tarifa))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[Response(status: 201, type: 'array{message:string,data:array{tarifa:TarifaResource},errors:null,code:string}')]
    public function store(GuardarTarifaRequest $request, GestionarTarifas $gestion): JsonResponse
    {
        $tarifa = $gestion->crear($this->actor($request), $request->validated());
        $tarifa->load(['anioEscolar:id,nombre', 'grado:id,nombre']);

        return response()->json([
            'message' => 'Tarifa creada.',
            'data' => ['tarifa' => (new TarifaResource($tarifa))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    #[Response(status: 200, type: 'array{message:string,data:array{tarifa:TarifaResource},errors:null,code:string}')]
    public function update(
        GuardarTarifaRequest $request,
        Tarifa $tarifa,
        GestionarTarifas $gestion,
    ): JsonResponse {
        $actualizada = $gestion->actualizar($this->actor($request), $tarifa, $request->validated());
        $actualizada->load(['anioEscolar:id,nombre', 'grado:id,nombre']);

        return response()->json([
            'message' => 'Tarifa actualizada.',
            'data' => ['tarifa' => (new TarifaResource($actualizada))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[Response(status: 200, type: 'array{message:string,data:null,errors:null,code:string}')]
    public function destroy(Tarifa $tarifa, Request $request, GestionarTarifas $gestion): JsonResponse
    {
        Gate::authorize('delete', $tarifa);
        $gestion->eliminar($this->actor($request), $tarifa);

        return response()->json([
            'message' => 'Tarifa eliminada.',
            'data' => null,
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
