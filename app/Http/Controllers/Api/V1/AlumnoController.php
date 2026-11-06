<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarAlumnoRequest;
use App\Http\Requests\CargarFotoAlumnoRequest;
use App\Http\Requests\GuardarAlumnoRequest;
use App\Http\Requests\ListarAlumnosRequest;
use App\Http\Resources\Api\V1\AlumnoListadoResource;
use App\Http\Resources\Api\V1\AlumnoResource;
use App\Http\Resources\Api\V1\HistorialEstadoAlumnoResource;
use App\Http\Resources\Api\V1\HistorialMatriculaResource;
use App\Http\Resources\Api\V1\MatriculaResource;
use App\Models\Alumno;
use App\Models\HistorialEstadoAlumno;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Policies\AlumnoPolicy;
use App\Services\GestionarAlumnos;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AlumnoController extends Controller
{
    public function index(ListarAlumnosRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $actor = $this->actor($request);
        $consulta = Alumno::query()
            ->where('colegio_id', $actor->colegio_id)
            ->when($filtros['buscar'] ?? null, function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->where('nombre', 'like', '%'.$buscar.'%')
                        ->orWhere('apellido', 'like', '%'.$buscar.'%')
                        ->orWhere('documento_identidad', 'like', '%'.$buscar.'%');
                });
            })
            ->when(isset($filtros['estado']), fn ($query) => $query->where('estado', $filtros['estado']))
            ->when(
                isset($filtros['grado_id']) || isset($filtros['seccion_id']) || isset($filtros['anio_escolar_id']),
                fn ($query) => $query->whereHas('matriculas', function ($query) use ($filtros): void {
                    $query->whereIn('estado', ['pre_inscrito', 'pendiente_documentos', 'matriculado'])
                        ->when(isset($filtros['grado_id']), fn ($query) => $query->where('grado_id', $filtros['grado_id']))
                        ->when(isset($filtros['seccion_id']), fn ($query) => $query->where('seccion_id', $filtros['seccion_id']))
                        ->when(isset($filtros['anio_escolar_id']), fn ($query) => $query->where('anio_escolar_id', $filtros['anio_escolar_id']));
                }),
            )
            ->with(['matriculas' => fn ($query) => $query
                ->select([
                    'id',
                    'alumno_id',
                    'anio_escolar',
                    'anio_escolar_id',
                    'grado',
                    'grado_id',
                    'seccion',
                    'seccion_id',
                    'estado',
                ])
                ->whereIn('estado', ['pre_inscrito', 'pendiente_documentos', 'matriculado'])
                ->orderByDesc('anio_escolar_id')
                ->orderByDesc('id')]);

        if ($actor->hasAnyRole(['Docente', 'Auxiliar'])) {
            $secciones = app(AlumnoPolicy::class)->seccionesAsignadas($actor);
            $consulta->whereHas('matriculas', fn ($query) => $query
                ->whereIn('seccion_id', $secciones)
                ->whereIn('estado', ['pre_inscrito', 'pendiente_documentos', 'matriculado']));
        }

        $consulta = $this->ordenarLista($consulta, $filtros, [['apellido', 'asc'], ['nombre', 'asc'], ['id', 'asc']]);
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Alumnos del colegio.',
            'data' => [
                'alumnos' => AlumnoListadoResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(GuardarAlumnoRequest $request, GestionarAlumnos $gestion): JsonResponse
    {
        $alumno = $gestion->crear($this->actor($request), $request->validated());

        return response()->json([
            'message' => 'Alumno creado.',
            'data' => ['alumno' => (new AlumnoResource($alumno))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $alumno = Alumno::query()->findOrFail($id);
        Gate::authorize('view', $alumno);

        return response()->json([
            'message' => 'Alumno encontrado.',
            'data' => ['alumno' => (new AlumnoResource($alumno))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function update(
        ActualizarAlumnoRequest $request,
        string $id,
        GestionarAlumnos $gestion,
    ): JsonResponse {
        $alumno = Alumno::query()->findOrFail($id);
        $actualizado = $gestion->actualizar($this->actor($request), $alumno, $request->validated());

        return response()->json([
            'message' => 'Alumno actualizado.',
            'data' => ['alumno' => (new AlumnoResource($actualizado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function historial(Alumno $alumno, Request $request): JsonResponse
    {
        Gate::authorize('historial', $alumno);
        $secciones = $this->actor($request)->hasAnyRole(['Docente', 'Auxiliar'])
            ? app(AlumnoPolicy::class)->seccionesAsignadas($this->actor($request))
            : null;

        $matriculas = $alumno->matriculas()
            ->when($secciones !== null, fn ($query) => $query->whereIn('seccion_id', $secciones))
            ->with([
                'alumno:id,nombre,apellido',
                'anioEscolar:id,nombre',
                'gradoRegistro:id,nombre',
                'seccionRegistro:id,nombre',
                'historial',
            ])
            ->orderByDesc('anio_escolar_id')
            ->get();
        $estados = HistorialEstadoAlumno::query()
            ->where('alumno_id', $alumno->id)
            ->orderByDesc('fecha')
            ->get();

        return response()->json([
            'message' => 'Historial del alumno.',
            'data' => [
                'matriculas' => MatriculaResource::collection($matriculas)->resolve($request),
                'cambios_estado' => HistorialEstadoAlumnoResource::collection($estados)->resolve($request),
                'cambios_matricula' => HistorialMatriculaResource::collection(
                    $matriculas->flatMap(fn (Matricula $matricula) => $matricula->historial),
                )->resolve($request),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function cargarFoto(
        CargarFotoAlumnoRequest $request,
        Alumno $alumno,
        GestionarAlumnos $gestion,
    ): JsonResponse {
        $actualizado = $gestion->guardarFoto($this->actor($request), $alumno, $request->file('foto'));

        return response()->json([
            'message' => 'Foto del alumno actualizada.',
            'data' => ['foto_url' => route('api.v1.alumnos.foto', $actualizado->id)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function foto(Alumno $alumno): BinaryFileResponse
    {
        Gate::authorize('photo', $alumno);

        if ($alumno->foto_path === null || ! Storage::disk('local')->exists($alumno->foto_path)) {
            abort(404);
        }

        $mime = Storage::disk('local')->mimeType($alumno->foto_path);

        abort_unless(in_array($mime, ['image/jpeg', 'image/png'], true), 404);

        return response()->file(Storage::disk('local')->path($alumno->foto_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
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
