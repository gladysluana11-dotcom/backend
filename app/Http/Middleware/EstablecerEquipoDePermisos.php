<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablecerEquipoDePermisos
{
    public function handle(Request $request, Closure $next): Response
    {
        setPermissionsTeamId($request->user()?->colegio_id);

        try {
            return $next($request);
        } finally {
            setPermissionsTeamId(null);
        }
    }
}
