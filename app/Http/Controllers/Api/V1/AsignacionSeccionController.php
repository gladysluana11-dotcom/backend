<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarAsignacionSeccionRequest;
use App\Http\Resources\Api\V1\AsignacionSeccionResource;
use App\Models\AsignacionSeccion;
use App\Models\Usuario;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AsignacionSeccionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAssignments', AsignacionSeccion::class);
        $filtros = $request->validate([
            'anio_escolar_id' => ['sometimes', 'integer', 'min:1'],
            'seccion_id' => ['sometimes', 'integer', 'min:1'],
            'usuario_id' => ['sometimes', 'integer', 'min:1'],
            'activo' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginador = AsignacionSeccion::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(isset($filtros['anio_escolar_id']), fn ($query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']))
            ->when(isset($filtros['seccion_id']), fn ($query) => $query->where('seccion_id', $filtros['seccion_id']))
            ->when(isset($filtros['usuario_id']), fn ($query) => $query->where('usuario_id', $filtros['usuario_id']))
            ->when(isset($filtros['activo']), fn ($query) => $query->where('activo', $filtros['activo']))
            ->orderBy('anio_escolar_id')
            ->orderBy('seccion_id')
            ->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Asignaciones de sección del colegio.',
            'data' => [
                'asignaciones' => AsignacionSeccionResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(GuardarAsignacionSeccionRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $datos = $request->validated();
        $usuario = Usuario::query()
            ->where('colegio_id', $actor->colegio_id)
            ->findOrFail($datos['usuario_id']);

        if (! $usuario->hasAnyRole(['Docente', 'Auxiliar'])) {
            throw ValidationException::withMessages([
                'usuario_id' => ['La asignación debe corresponder a un docente o auxiliar.'],
            ]);
        }

        $asignacion = AsignacionSeccion::query()->firstOrNew([
            'colegio_id' => $actor->colegio_id,
            'anio_escolar_id' => $datos['anio_escolar_id'],
            'seccion_id' => $datos['seccion_id'],
            'usuario_id' => $datos['usuario_id'],
        ]);
        $asignacion->forceFill(['activo' => true])->save();

        return response()->json([
            'message' => 'Asignación de sección creada.',
            'data' => ['asignacion' => (new AsignacionSeccionResource($asignacion))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
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
