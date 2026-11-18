<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularDescuentoRequest;
use App\Http\Requests\GuardarDescuentoRequest;
use App\Http\Resources\Api\V1\DescuentoResource;
use App\Models\Cuota;
use App\Models\Descuento;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Services\GestionarDescuentos;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DescuentoController extends Controller
{
    #[Response(status: 201, type: 'array{message:string,data:array{descuento:DescuentoResource},errors:null,code:string}')]
    public function storeCuota(
        GuardarDescuentoRequest $request,
        Cuota $cuota,
        GestionarDescuentos $gestion,
    ): JsonResponse {
        $descuento = $gestion->aplicarACuota($this->actor($request), $cuota, $request->validated());

        return response()->json([
            'message' => 'Descuento aplicado a la cuota.',
            'data' => ['descuento' => (new DescuentoResource($descuento))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    #[Response(status: 201, type: 'array{message:string,data:array{descuentos:list<DescuentoResource>},errors:null,code:string}')]
    public function storeMatricula(
        GuardarDescuentoRequest $request,
        Matricula $matricula,
        GestionarDescuentos $gestion,
    ): JsonResponse {
        $descuentos = $gestion->aplicarAMatricula($this->actor($request), $matricula, $request->validated());

        return response()->json([
            'message' => 'Descuento aplicado a las pensiones pendientes y parciales.',
            'data' => ['descuentos' => DescuentoResource::collection($descuentos)->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    #[Response(status: 200, type: 'array{message:string,data:array{descuento:DescuentoResource},errors:null,code:string}')]
    public function anular(
        AnularDescuentoRequest $request,
        Descuento $descuento,
        GestionarDescuentos $gestion,
    ): JsonResponse {
        $anulado = $gestion->anular($this->actor($request), $descuento, $request->validated()['motivo']);

        return response()->json([
            'message' => 'Descuento anulado.',
            'data' => ['descuento' => (new DescuentoResource($anulado))->resolve($request)],
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
