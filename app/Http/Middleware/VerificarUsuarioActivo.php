<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario || $usuario->activo) {
            return $next($request);
        }

        if (
            $request->routeIs('api.v1.auth.logout')
            && $request->hasSession()
            && $request->bearerToken() === null
        ) {
            return $next($request);
        }

        if ($request->hasSession() && $request->bearerToken() === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'El usuario está desactivado.',
            'data' => null,
            'errors' => null,
            'code' => 'USER_INACTIVE',
        ], 403);
    }
}
