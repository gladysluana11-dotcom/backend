<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiereCambioContrasena
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (
            $usuario instanceof Usuario
            && $usuario->requiere_cambio_contrasena
            && ! $request->routeIs('api.v1.auth.cambiar-contrasena', 'api.v1.auth.logout')
        ) {
            return response()->json([
                'message' => 'Debes cambiar tu contraseña antes de continuar.',
                'data' => null,
                'errors' => null,
                'code' => 'PASSWORD_CHANGE_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
