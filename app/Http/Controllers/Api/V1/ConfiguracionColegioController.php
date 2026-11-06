<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarConfiguracionColegioRequest;
use App\Http\Resources\Api\V1\ConfiguracionColegioResource;
use App\Models\ConfiguracionColegio;
use App\Models\Usuario;
use App\Services\GestionarConfiguracionColegio;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConfiguracionColegioController extends Controller
{
    public function show(Request $request, GestionarConfiguracionColegio $configuraciones): JsonResponse
    {
        Gate::authorize('viewAny', ConfiguracionColegio::class);
        $configuracion = $configuraciones->obtenerConfiguracion($this->actor($request));

        return response()->json([
            'message' => 'Configuración del colegio.',
            'data' => ['configuracion' => (new ConfiguracionColegioResource($configuracion))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function update(
        ActualizarConfiguracionColegioRequest $request,
        GestionarConfiguracionColegio $configuraciones,
    ): JsonResponse {
        $configuracion = $configuraciones->actualizarConfiguracion(
            $this->actor($request),
            $request->validated(),
        );

        return response()->json([
            'message' => 'Configuración del colegio actualizada.',
            'data' => ['configuracion' => (new ConfiguracionColegioResource($configuracion))->resolve($request)],
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
