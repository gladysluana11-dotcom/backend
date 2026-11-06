<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarApoderadoRequest;
use App\Http\Requests\GuardarApoderadoRequest;
use App\Http\Requests\ListarApoderadosRequest;
use App\Http\Requests\VincularApoderadoRequest;
use App\Http\Resources\Api\V1\ApoderadoResource;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Usuario;
use App\Services\GestionarApoderados;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApoderadoController extends Controller
{
    public function index(ListarApoderadosRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $actor = $this->actor($request);
        $consulta = Apoderado::query()
            ->where('colegio_id', $actor->colegio_id)
            ->when($filtros['buscar'] ?? null, function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->where('nombre', 'like', '%'.$buscar.'%')
                        ->orWhere('apellido', 'like', '%'.$buscar.'%');
                });
            })
            ->with(['alumnos:id,nombre,apellido']);

        $consulta = $this->ordenarLista($consulta, $filtros, [['apellido', 'asc'], ['nombre', 'asc'], ['id', 'asc']]);
        $paginador = $consulta->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Apoderados del colegio.',
            'data' => [
                'apoderados' => ApoderadoResource::collection($paginador)->resolve($request),
                'paginacion' => $this->datosPaginacion($paginador),
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function show(Apoderado $apoderado, Request $request): JsonResponse
    {
        Gate::authorize('view', $apoderado);
        $apoderado->load(['alumnos:id,nombre,apellido']);

        return response()->json([
            'message' => 'Apoderado encontrado.',
            'data' => ['apoderado' => (new ApoderadoResource($apoderado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function crearYVincular(
        GuardarApoderadoRequest $request,
        Alumno $alumno,
        GestionarApoderados $gestion,
    ): JsonResponse {
        $apoderado = $gestion->crearYVincular($this->actor($request), $alumno, $request->validated());

        return response()->json([
            'message' => 'Apoderado creado y vinculado al alumno.',
            'data' => ['apoderado' => (new ApoderadoResource($apoderado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function vincular(
        VincularApoderadoRequest $request,
        Alumno $alumno,
        Apoderado $apoderado,
        GestionarApoderados $gestion,
    ): JsonResponse {
        $actualizado = $gestion->vincular(
            $this->actor($request),
            $alumno,
            $apoderado,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Apoderado vinculado al alumno.',
            'data' => ['apoderado' => (new ApoderadoResource($actualizado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function update(
        ActualizarApoderadoRequest $request,
        Apoderado $apoderado,
        GestionarApoderados $gestion,
    ): JsonResponse {
        $actualizado = $gestion->actualizar($this->actor($request), $apoderado, $request->validated());

        return response()->json([
            'message' => 'Apoderado actualizado.',
            'data' => ['apoderado' => (new ApoderadoResource($actualizado))->resolve($request)],
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
