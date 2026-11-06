<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarUsuarioRequest;
use App\Http\Requests\CrearUsuarioRequest;
use App\Http\Requests\ListarUsuariosRequest;
use App\Http\Resources\Api\V1\UsuarioResource;
use App\Models\Usuario;
use App\Services\GestionarUsuarios;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UsuarioController extends Controller
{
    public function index(ListarUsuariosRequest $request, GestionarUsuarios $usuarios): JsonResponse
    {
        $paginador = $usuarios->listar($this->actor($request), $request->validated());

        return response()->json([
            'message' => 'Usuarios del colegio.',
            'data' => [
                'usuarios' => UsuarioResource::collection($paginador)->resolve($request),
                'paginacion' => [
                    'pagina' => $paginador->currentPage(),
                    'por_pagina' => $paginador->perPage(),
                    'total' => $paginador->total(),
                    'ultima_pagina' => $paginador->lastPage(),
                    'siguiente' => $paginador->nextPageUrl(),
                    'anterior' => $paginador->previousPageUrl(),
                ],
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(CrearUsuarioRequest $request, GestionarUsuarios $usuarios): JsonResponse
    {
        $resultado = $usuarios->crear($this->actor($request), $request->validated());

        return response()->json([
            'message' => 'Usuario creado. Comparte la contraseña temporal de forma segura.',
            'data' => [
                'usuario' => (new UsuarioResource($resultado['usuario']))->resolve($request),
                'contrasena_temporal' => $resultado['contrasena_temporal'],
            ],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function show(string $usuario, Request $request, GestionarUsuarios $usuarios): JsonResponse
    {
        $actor = $this->actor($request);
        $registro = $usuarios->buscar($actor, $usuario);
        Gate::authorize('view', $registro);

        return response()->json([
            'message' => 'Usuario encontrado.',
            'data' => ['usuario' => (new UsuarioResource($registro))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function update(
        ActualizarUsuarioRequest $request,
        string $usuario,
        GestionarUsuarios $usuarios,
    ): JsonResponse {
        $actor = $this->actor($request);
        $registro = $usuarios->buscar($actor, $usuario);
        Gate::authorize('update', $registro);
        $actualizado = $usuarios->actualizar($actor, $registro, $request->validated());

        return response()->json([
            'message' => 'Usuario actualizado.',
            'data' => ['usuario' => (new UsuarioResource($actualizado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function desactivar(Request $request, string $usuario, GestionarUsuarios $usuarios): JsonResponse
    {
        $actor = $this->actor($request);
        $registro = $usuarios->buscar($actor, $usuario);
        Gate::authorize('deactivate', $registro);
        $desactivado = $usuarios->desactivar($actor, $registro);

        return response()->json([
            'message' => 'Usuario desactivado.',
            'data' => ['usuario' => (new UsuarioResource($desactivado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function reactivar(Request $request, string $usuario, GestionarUsuarios $usuarios): JsonResponse
    {
        $actor = $this->actor($request);
        $registro = $usuarios->buscar($actor, $usuario);
        Gate::authorize('reactivate', $registro);
        $reactivado = $usuarios->reactivar($actor, $registro);

        return response()->json([
            'message' => 'Usuario reactivado.',
            'data' => ['usuario' => (new UsuarioResource($reactivado))->resolve($request)],
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
