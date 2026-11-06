<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RolResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof Usuario && $usuario->colegio_id !== null, 403);

        $roles = Role::query()
            ->where('colegio_id', $usuario->colegio_id)
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Roles del colegio.',
            'data' => ['roles' => RolResource::collection($roles)->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }
}
