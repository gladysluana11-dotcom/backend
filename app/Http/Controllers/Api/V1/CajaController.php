<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CerrarCajaRequest;
use App\Http\Requests\ConsultarCajaRequest;
use App\Http\Resources\Api\V1\CierreCajaResource;
use App\Models\CierreCaja;
use App\Models\Usuario;
use App\Services\GestionarCaja;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CajaController extends Controller
{
    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{fecha:string,total:string,cantidad_pagos:int,por_usuario:list<array{usuario_id:int,usuario:string,cantidad:int,total:string}>,por_medio:list<array{medio:string,cantidad:int,total:string}>},errors:null,code:string}',
    )]
    public function resumen(
        ConsultarCajaRequest $request,
        GestionarCaja $caja,
    ): JsonResponse {
        Gate::authorize('viewAny', CierreCaja::class);
        $resumen = $caja->resumen(
            $this->actor($request),
            $request->validated('fecha', today()->toDateString()),
        );

        return response()->json([
            'message' => 'Resumen de caja.',
            'data' => $resumen,
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[ScrambleResponse(status: 201, type: 'array{message:string,data:array{cierre:CierreCajaResource},errors:null,code:string}')]
    public function cerrar(CerrarCajaRequest $request, GestionarCaja $caja): JsonResponse
    {
        $cierre = $caja->cerrar(
            $this->actor($request),
            $request->validated('fecha', today()->toDateString()),
            $request->validated('observacion'),
        );

        return response()->json([
            'message' => 'Caja cerrada.',
            'data' => ['cierre' => (new CierreCajaResource($cierre))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
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
