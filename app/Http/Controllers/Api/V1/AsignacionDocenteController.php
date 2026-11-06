<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarAsignacionDocenteRequest;
use App\Http\Requests\ListarAsignacionesDocentesRequest;
use App\Http\Resources\Api\V1\AsignacionDocenteResource;
use App\Models\AsignacionDocente;
use App\Models\Usuario;
use App\Services\GestionarEstructuraAcademica;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AsignacionDocenteController extends Controller
{
    public function index(ListarAsignacionesDocentesRequest $request): JsonResponse
    {
        Gate::authorize('viewAssignments', AsignacionDocente::class);
        $filtros = $request->validated();
        $consulta = AsignacionDocente::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->when(isset($filtros['anio_escolar_id']), fn ($query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']))
            ->when(isset($filtros['seccion_id']), fn ($query) => $query->where('seccion_id', $filtros['seccion_id']))
            ->when(isset($filtros['curso_id']), fn ($query) => $query->where('curso_id', $filtros['curso_id']))
            ->when(isset($filtros['docente_id']), fn ($query) => $query->where('docente_id', $filtros['docente_id']))
            ->when(isset($filtros['activo']), fn ($query) => $query->where('activo', $filtros['activo']))
            ->when($filtros['buscar'] ?? null, function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereHas('docente', function ($query) use ($buscar): void {
                        $query->where('nombre', 'like', '%'.$buscar.'%')
                            ->orWhere('apellido', 'like', '%'.$buscar.'%');
                    })->orWhereHas('curso', fn ($query) => $query->where('nombre', 'like', '%'.$buscar.'%'))
                        ->orWhereHas('seccion', fn ($query) => $query->where('nombre', 'like', '%'.$buscar.'%'));
                });
            })
            ->with(['docente:id,nombre,apellido', 'curso:id,nombre', 'seccion:id,nombre']);
        $consulta = $this->ordenarLista(
            $consulta,
            $filtros,
            [['anio_escolar_id', 'asc'], ['seccion_id', 'asc'], ['curso_id', 'asc'], ['id', 'asc']],
        );
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Asignaciones docentes del colegio.',
            'data' => [
                'asignaciones' => AsignacionDocenteResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function misAsignaciones(ListarAsignacionesDocentesRequest $request): JsonResponse
    {
        Gate::authorize('viewOwnAssignments', AsignacionDocente::class);
        $filtros = $request->validated();
        $consulta = AsignacionDocente::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->where('docente_id', $this->actor($request)->id)
            ->where('activo', true)
            ->when(isset($filtros['anio_escolar_id']), fn ($query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']))
            ->when(isset($filtros['seccion_id']), fn ($query) => $query->where('seccion_id', $filtros['seccion_id']))
            ->when(isset($filtros['curso_id']), fn ($query) => $query->where('curso_id', $filtros['curso_id']))
            ->when($filtros['buscar'] ?? null, function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereHas('curso', fn ($query) => $query->where('nombre', 'like', '%'.$buscar.'%'))
                        ->orWhereHas('seccion', fn ($query) => $query->where('nombre', 'like', '%'.$buscar.'%'));
                });
            })
            ->with(['curso:id,nombre', 'seccion:id,nombre', 'anioEscolar:id,nombre']);
        $consulta = $this->ordenarLista(
            $consulta,
            $filtros,
            [['anio_escolar_id', 'asc'], ['seccion_id', 'asc'], ['curso_id', 'asc'], ['id', 'asc']],
        );
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Asignaciones del docente autenticado.',
            'data' => [
                'asignaciones' => AsignacionDocenteResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(
        GuardarAsignacionDocenteRequest $request,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        $asignacion = $estructura->crear(
            $this->actor($request),
            new AsignacionDocente,
            $request->validated(),
        );
        $asignacion->load(['docente:id,nombre,apellido', 'curso:id,nombre', 'seccion:id,nombre']);

        return response()->json([
            'message' => 'Asignación docente creada.',
            'data' => ['asignacion' => (new AsignacionDocenteResource($asignacion))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function update(
        GuardarAsignacionDocenteRequest $request,
        AsignacionDocente $asignacion,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        $actualizada = $estructura->actualizar(
            $this->actor($request),
            $asignacion,
            $request->validated(),
        );
        $actualizada->load(['docente:id,nombre,apellido', 'curso:id,nombre', 'seccion:id,nombre']);

        return response()->json([
            'message' => 'Asignación docente actualizada.',
            'data' => ['asignacion' => (new AsignacionDocenteResource($actualizada))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function destroy(
        AsignacionDocente $asignacion,
        Request $request,
        GestionarEstructuraAcademica $estructura,
    ): JsonResponse {
        Gate::authorize('delete', $asignacion);
        $desactivada = $estructura->desactivar($this->actor($request), $asignacion);

        return response()->json([
            'message' => 'Asignación docente desactivada.',
            'data' => ['asignacion' => (new AsignacionDocenteResource($desactivada))->resolve($request)],
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
