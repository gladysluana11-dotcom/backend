<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Colegio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColegioController
{
    public function publicInfo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subdominio' => ['required', 'string', 'alpha_dash', 'max:63'],
        ]);

        $colegio = Colegio::query()
            ->where('subdominio', $validated['subdominio'])
            ->where('activo', true)
            ->firstOrFail();

        return response()->json([
            'message' => 'Identidad del colegio.',
            'data' => [
                'nombre' => $colegio->nombre,
                'logo' => $colegio->logo,
                'colores' => $colegio->colores ?? [],
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }
}
