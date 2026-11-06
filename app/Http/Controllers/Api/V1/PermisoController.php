<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PermisoResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class PermisoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof Usuario && $usuario->colegio_id !== null, 403);

        $permisos = Role::query()
            ->where('colegio_id', $usuario->colegio_id)
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->get()
            ->flatMap(static fn (Role $rol) => $rol->permissions)
            ->unique('id')
            ->sortBy('name')
            ->values();

        return response()->json([
            'message' => 'Permisos disponibles para los roles del colegio.',
            'data' => ['permisos' => PermisoResource::collection($permisos)->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }
}
