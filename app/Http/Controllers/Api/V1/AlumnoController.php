<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Alumno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumnoController
{
    public function show(string $id): JsonResponse
    {
        $alumno = Alumno::query()->findOrFail($id);

        return response()->json([
            'message' => 'Alumno encontrado.',
            'data' => ['alumno' => $alumno],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $alumno = Alumno::query()->findOrFail($id);
        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'apellido' => ['sometimes', 'required', 'string', 'max:255'],
            'documento_identidad' => ['sometimes', 'nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['sometimes', 'nullable', 'date'],
            'sexo' => ['sometimes', 'nullable', 'string', 'max:20'],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'estado' => ['sometimes', 'required', 'in:activo,retirado,egresado'],
            'colegio_id' => ['prohibited'],
        ]);

        $alumno->update($validated);

        return response()->json([
            'message' => 'Alumno actualizado.',
            'data' => ['alumno' => $alumno->refresh()],
            'errors' => null,
            'code' => 'OK',
        ]);
    }
}
