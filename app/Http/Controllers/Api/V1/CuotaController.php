<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListarCuotasRequest;
use App\Http\Resources\Api\V1\CuotaResource;
use App\Models\Cuota;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Services\EstadoDeCuota;
use App\Services\GenerarCuotas;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class CuotaController extends Controller
{
    #[Response(
        status: 200,
        type: 'array{message:string,data:array{cuotas:list<CuotaResource>,paginacion:array{pagina:int,por_pagina:int,total:int,ultima_pagina:int,siguiente:string|null,anterior:string|null}},errors:null,code:string}',
    )]
    public function index(ListarCuotasRequest $request, EstadoDeCuota $estado): JsonResponse
    {
        $filtros = $request->validated();
        $usuario = $this->actor($request);
        $consulta = $this->consultaBase($usuario, $filtros);

        return $this->respuestaLista($request, $consulta, $filtros, $estado, 'Cuotas del colegio.');
    }

    #[Response(
        status: 200,
        type: 'array{message:string,data:array{cuotas:list<CuotaResource>,paginacion:array{pagina:int,por_pagina:int,total:int,ultima_pagina:int,siguiente:string|null,anterior:string|null}},errors:null,code:string}',
    )]
    public function porMatricula(
        ListarCuotasRequest $request,
        Matricula $matricula,
        EstadoDeCuota $estado,
    ): JsonResponse {
        Gate::authorize('viewAny', Cuota::class);
        $consulta = $this->consultaBase($this->actor($request), $request->validated())
            ->where('matricula_id', $matricula->id);

        return $this->respuestaLista(
            $request,
            $consulta,
            $request->validated(),
            $estado,
            'Cuotas de la matrícula.',
        );
    }

    #[Response(status: 200, type: 'array{message:string,data:array{cuotas:list<CuotaResource>},errors:null,code:string}')]
    public function generar(
        Matricula $matricula,
        Request $request,
        GenerarCuotas $generarCuotas,
        EstadoDeCuota $estadoDeCuota,
    ): JsonResponse {
        Gate::authorize('generateQuotas', $matricula);
        $cuotas = $generarCuotas->generar($matricula, $this->actor($request));
        $cuotas->load(['alumno:id,nombre,apellido', 'descuentos']);
        $estados = $estadoDeCuota->calcularVarias($cuotas);
        $cuotas->each(fn (Cuota $cuota) => $cuota->setRelation('estadoCalculado', $estados[$cuota->id]));

        return response()->json([
            'message' => $cuotas->isEmpty()
                ? 'No existe tarifa para el grado; el intento quedó auditado.'
                : 'Cuotas generadas o ya existentes.',
            'data' => ['cuotas' => CuotaResource::collection($cuotas)->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Cuota>
     */
    private function consultaBase(Usuario $usuario, array $filtros): Builder
    {
        $consulta = Cuota::query()
            ->where('colegio_id', $usuario->colegio_id)
            ->when(isset($filtros['alumno_id']), fn ($query) => $query->where('alumno_id', $filtros['alumno_id']))
            ->when(isset($filtros['matricula_id']), fn ($query) => $query->where('matricula_id', $filtros['matricula_id']))
            ->when(isset($filtros['anio_escolar_id']), fn ($query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']))
            ->when(isset($filtros['concepto']), fn ($query) => $query->where('concepto', $filtros['concepto']))
            ->when(isset($filtros['vencimiento_desde']), fn ($query) => $query->whereDate('vencimiento', '>=', $filtros['vencimiento_desde']))
            ->when(isset($filtros['vencimiento_hasta']), fn ($query) => $query->whereDate('vencimiento', '<=', $filtros['vencimiento_hasta']))
            ->with(['alumno:id,nombre,apellido', 'descuentos']);

        if (isset($filtros['orden'])) {
            return $consulta->orderBy('vencimiento', $filtros['orden'])->orderBy('id', $filtros['orden']);
        }

        return $this->ordenarLista(
            $consulta,
            $filtros,
            [['vencimiento', 'asc'], ['id', 'asc']],
        );
    }

    /**
     * @param  Builder<Cuota>  $consulta
     * @param  array<string, mixed>  $filtros
     */
    private function respuestaLista(
        ListarCuotasRequest $request,
        Builder $consulta,
        array $filtros,
        EstadoDeCuota $estado,
        string $mensaje,
    ): JsonResponse {
        $porPagina = $filtros['per_page'] ?? 25;

        if (isset($filtros['estado'])) {
            $todasLasCuotas = $consulta->get();
            $estados = $estado->calcularVarias($todasLasCuotas);
            $cuotasFiltradas = $todasLasCuotas
                ->filter(function (Cuota $cuota) use ($estados, $filtros): bool {
                    $cuota->setRelation('estadoCalculado', $estados[$cuota->id]);

                    return $estados[$cuota->id]['estado'] === $filtros['estado'];
                })
                ->values();
            $pagina = LengthAwarePaginator::resolveCurrentPage();
            $paginador = new LengthAwarePaginator(
                $cuotasFiltradas->forPage($pagina, $porPagina)->values(),
                $cuotasFiltradas->count(),
                $porPagina,
                $pagina,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        } else {
            $paginador = $consulta->paginate($porPagina);
            $estados = $estado->calcularVarias($paginador->getCollection());
            $paginador->getCollection()->each(
                fn (Cuota $cuota) => $cuota->setRelation('estadoCalculado', $estados[$cuota->id]),
            );
        }

        return response()->json([
            'message' => $mensaje,
            'data' => [
                'cuotas' => CuotaResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
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
