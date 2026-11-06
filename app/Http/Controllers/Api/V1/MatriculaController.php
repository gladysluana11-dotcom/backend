<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarEstadoMatriculaRequest;
use App\Http\Requests\CambiarSeccionMatriculaRequest;
use App\Http\Requests\GuardarMatriculaRequest;
use App\Http\Requests\ListarMatriculasRequest;
use App\Http\Resources\Api\V1\MatriculaResource;
use App\Models\AsignacionSeccion;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Services\GenerarFichaMatricula;
use App\Services\GestionarMatriculas;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MatriculaController extends Controller
{
    public function index(ListarMatriculasRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $actor = $this->actor($request);
        $consulta = Matricula::query()
            ->where('colegio_id', $actor->colegio_id)
            ->when(isset($filtros['anio_escolar_id']), fn ($query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']))
            ->when(isset($filtros['grado_id']), fn ($query) => $query->where('grado_id', $filtros['grado_id']))
            ->when(isset($filtros['seccion_id']), fn ($query) => $query->where('seccion_id', $filtros['seccion_id']))
            ->when(isset($filtros['alumno_id']), fn ($query) => $query->where('alumno_id', $filtros['alumno_id']))
            ->when(isset($filtros['estado']), fn ($query) => $query->where('estado', $filtros['estado']))
            ->when($filtros['buscar'] ?? null, function ($query, string $buscar): void {
                $query->whereHas('alumno', fn ($query) => $query
                    ->where('nombre', 'like', '%'.$buscar.'%')
                    ->orWhere('apellido', 'like', '%'.$buscar.'%'));
            })
            ->with([
                'alumno:id,nombre,apellido',
                'anioEscolar:id,nombre',
                'gradoRegistro:id,nombre',
                'seccionRegistro:id,nombre',
                'usuarioRegistro:id,nombre,apellido',
            ]);

        if ($actor->hasAnyRole(['Docente', 'Auxiliar'])) {
            $secciones = AsignacionSeccion::query()
                ->where('colegio_id', $actor->colegio_id)
                ->where('usuario_id', $actor->id)
                ->where('activo', true)
                ->select('seccion_id');
            $consulta->whereIn('seccion_id', $secciones);
        }

        $consulta = $this->ordenarLista(
            $consulta,
            $filtros,
            [['anio_escolar_id', 'desc'], ['grado_id', 'asc'], ['seccion_id', 'asc'], ['id', 'asc']],
        );
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Matrículas del colegio.',
            'data' => [
                'matriculas' => MatriculaResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(GuardarMatriculaRequest $request, GestionarMatriculas $gestion): JsonResponse
    {
        $matricula = $gestion->crear($this->actor($request), $request->validated());
        $matricula->load([
            'alumno:id,nombre,apellido',
            'anioEscolar:id,nombre',
            'gradoRegistro:id,nombre',
            'seccionRegistro:id,nombre',
            'usuarioRegistro:id,nombre,apellido',
            'documentos.documentoRequerido:id,nombre',
        ]);

        return response()->json([
            'message' => 'Matrícula creada.',
            'data' => ['matricula' => (new MatriculaResource($matricula))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function show(Matricula $matricula, Request $request): JsonResponse
    {
        Gate::authorize('view', $matricula);
        $matricula->load([
            'alumno:id,nombre,apellido',
            'anioEscolar:id,nombre',
            'gradoRegistro:id,nombre',
            'seccionRegistro:id,nombre',
            'usuarioRegistro:id,nombre,apellido',
            'documentos.documentoRequerido:id,nombre',
        ]);

        return response()->json([
            'message' => 'Matrícula encontrada.',
            'data' => ['matricula' => (new MatriculaResource($matricula))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function cambiarEstado(
        CambiarEstadoMatriculaRequest $request,
        Matricula $matricula,
        GestionarMatriculas $gestion,
    ): JsonResponse {
        $actualizada = $gestion->cambiarEstado($this->actor($request), $matricula, $request->validated());
        $actualizada->load([
            'alumno:id,nombre,apellido',
            'anioEscolar:id,nombre',
            'gradoRegistro:id,nombre',
            'seccionRegistro:id,nombre',
            'usuarioRegistro:id,nombre,apellido',
            'documentos.documentoRequerido:id,nombre',
        ]);

        return response()->json([
            'message' => 'Estado de matrícula actualizado.',
            'data' => ['matricula' => (new MatriculaResource($actualizada))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function cambiarSeccion(
        CambiarSeccionMatriculaRequest $request,
        Matricula $matricula,
        GestionarMatriculas $gestion,
    ): JsonResponse {
        $actualizada = $gestion->cambiarSeccion($this->actor($request), $matricula, $request->validated());
        $actualizada->load([
            'alumno:id,nombre,apellido',
            'anioEscolar:id,nombre',
            'gradoRegistro:id,nombre',
            'seccionRegistro:id,nombre',
            'documentos.documentoRequerido:id,nombre',
        ]);

        return response()->json([
            'message' => 'Sección de matrícula actualizada.',
            'data' => ['matricula' => (new MatriculaResource($actualizada))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function ficha(Matricula $matricula, GenerarFichaMatricula $ficha): Response
    {
        Gate::authorize('view', $matricula);

        return $ficha->descargar($matricula);
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
