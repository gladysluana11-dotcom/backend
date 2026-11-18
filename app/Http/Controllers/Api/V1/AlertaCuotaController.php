<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\ConsultarAlertasCuota;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AlertaCuotaController extends Controller
{
    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{dias_configurados:int,por_vencer:array{cantidad:int,monto:string},vencidas:array{cantidad:int,monto:string}},errors:null,code:string}',
    )]
    public function index(Request $request, ConsultarAlertasCuota $alertas): JsonResponse
    {
        Gate::authorize('ver-deudas');
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw new AuthenticationException;
        }

        return response()->json([
            'message' => 'Alertas de cuotas.',
            'data' => $alertas->consultar($usuario),
            'errors' => null,
            'code' => 'OK',
        ]);
    }
}
