<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarFuncionesColegioRequest;
use App\Http\Resources\Api\V1\FuncionColegioResource;
use App\Models\FuncionColegio;
use App\Models\Usuario;
use App\Services\GestionarConfiguracionColegio;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FuncionColegioController extends Controller
{
    public function show(Request $request, GestionarConfiguracionColegio $configuraciones): JsonResponse
    {
        Gate::authorize('viewAny', FuncionColegio::class);
        $funciones = $configuraciones->obtenerFunciones($this->actor($request));

        return response()->json([
            'message' => 'Funciones activables del colegio.',
            'data' => ['funciones' => (new FuncionColegioResource($funciones))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function update(
        ActualizarFuncionesColegioRequest $request,
        GestionarConfiguracionColegio $configuraciones,
    ): JsonResponse {
        $funciones = $configuraciones->actualizarFunciones(
            $this->actor($request),
            $request->validated(),
        );

        return response()->json([
            'message' => 'Funciones del colegio actualizadas.',
            'data' => ['funciones' => (new FuncionColegioResource($funciones))->resolve($request)],
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
