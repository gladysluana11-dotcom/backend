<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularPagoRequest;
use App\Http\Requests\ListarPagosRequest;
use App\Http\Requests\RegistrarPagoRequest;
use App\Http\Resources\Api\V1\PagoResource;
use App\Models\Pago;
use App\Models\Usuario;
use App\Services\AnularPago;
use App\Services\GenerarConstanciaPago;
use App\Services\RegistrarPago as RegistrarPagoService;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PagoController extends Controller
{
    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{pagos:list<PagoResource>,paginacion:array{pagina:int,por_pagina:int,total:int,ultima_pagina:int,siguiente:string|null,anterior:string|null}},errors:null,code:string}',
    )]
    public function index(ListarPagosRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $paginador = Pago::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(isset($filtros['alumno_id']), fn ($query) => $query->where('alumno_id', $filtros['alumno_id']))
            ->when(isset($filtros['matricula_id']), fn ($query) => $query->where('matricula_id', $filtros['matricula_id']))
            ->when(isset($filtros['desde']), fn ($query) => $query->whereDate('fecha_pago', '>=', $filtros['desde']))
            ->when(isset($filtros['hasta']), fn ($query) => $query->whereDate('fecha_pago', '<=', $filtros['hasta']))
            ->when(isset($filtros['medio']), fn ($query) => $query->where('medio', $filtros['medio']))
            ->when(isset($filtros['usuario_id']), fn ($query) => $query->where('usuario_id', $filtros['usuario_id']))
            ->when(isset($filtros['estado']), fn ($query) => $query->where('estado', $filtros['estado']))
            ->with('aplicaciones.cuota')
            ->when(
                isset($filtros['orden']),
                fn ($query) => $query->orderBy('fecha_pago', $filtros['orden'])->orderBy('id', $filtros['orden']),
                fn ($query) => $query->when(
                    isset($filtros['sort']),
                    fn ($query) => $query->orderBy($filtros['sort'], $filtros['direction'] ?? 'asc'),
                    fn ($query) => $query->orderByDesc('fecha_pago')->orderByDesc('id'),
                ),
            )
            ->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Pagos del colegio.',
            'data' => [
                'pagos' => PagoResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[ScrambleResponse(
        status: 201,
        type: 'array{message:string,data:array{pago:PagoResource,saldos:list<array{cuota_id:int,saldo:string,estado:string}>,constancia:array{numero:string,url:string}},errors:null,code:string}',
    )]
    #[ScrambleResponse(status: 409, type: 'array{message:string,data:null,errors:null,code:string}')]
    #[HeaderParameter('Idempotency-Key', 'Identificador único de la solicitud, válido durante 24 horas.', true, type: 'string')]
    public function store(RegistrarPagoRequest $request, RegistrarPagoService $registrar): JsonResponse
    {
        $resultado = $registrar->registrar(
            $this->actor($request),
            $request->safe()->except('idempotency_key'),
            (string) $request->header('Idempotency-Key'),
        );

        return response()->json((object) $resultado['respuesta'], 201);
    }

    #[ScrambleResponse(status: 200, type: 'array{message:string,data:array{pago:PagoResource},errors:null,code:string}')]
    public function show(Pago $pago, Request $request): JsonResponse
    {
        Gate::authorize('view', $pago);
        $pago->load('aplicaciones.cuota');

        return response()->json([
            'message' => 'Pago encontrado.',
            'data' => ['pago' => (new PagoResource($pago))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{pago:PagoResource,saldos:list<array{cuota_id:int,saldo:string,estado:string}>},errors:null,code:string}',
    )]
    public function anular(
        AnularPagoRequest $request,
        Pago $pago,
        AnularPago $anular,
    ): JsonResponse {
        $resultado = $anular->anular($this->actor($request), $pago, $request->validated('motivo'));

        return response()->json([
            'message' => 'Pago anulado.',
            'data' => [
                'pago' => (new PagoResource($resultado['pago']))->resolve($request),
                'saldos' => $resultado['saldos'],
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[ScrambleResponse(
        status: 200,
        description: 'Constancia de pago en formato PDF.',
        mediaType: 'application/pdf',
        type: 'string',
        format: 'binary',
    )]
    public function constancia(Pago $pago, GenerarConstanciaPago $constancia): Response
    {
        Gate::authorize('view', $pago);

        return $constancia->descargar($pago);
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
