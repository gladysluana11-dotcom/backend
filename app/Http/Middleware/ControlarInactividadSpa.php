<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ControlarInactividadSpa
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $ultimoAcceso = $session->get('actividad_ultima_vez');
        $minutosInactividad = max(1, (int) config('session.lifetime', 30));

        if (
            is_numeric($ultimoAcceso)
            && now()->timestamp - (int) $ultimoAcceso >= $minutosInactividad * 60
        ) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return response()->json([
                'message' => 'La sesión venció por inactividad. Inicia sesión nuevamente.',
                'data' => null,
                'errors' => null,
                'code' => 'SESSION_EXPIRED',
            ], 401);
        }

        $session->put('actividad_ultima_vez', now()->timestamp);

        return $next($request);
    }
}
