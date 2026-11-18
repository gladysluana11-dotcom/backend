<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarGestionCobranzaRequest;
use App\Http\Resources\Api\V1\GestionCobranzaResource;
use App\Models\Alumno;
use App\Models\GestionCobranza;
use App\Models\Usuario;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GestionCobranzaController extends Controller
{
    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{gestiones:list<GestionCobranzaResource>,paginacion:array{pagina:int,por_pagina:int,total:int,ultima_pagina:int}},errors:null,code:string}',
    )]
    public function index(Alumno $alumno, Request $request): JsonResponse
    {
        Gate::authorize('viewAny', GestionCobranza::class);
        $filtros = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginador = GestionCobranza::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->where('alumno_id', $alumno->id)
            ->with('usuario:id,nombre,apellido')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'message' => 'Gestiones de cobranza del alumno.',
            'data' => [
                'gestiones' => GestionCobranzaResource::collection($paginador)->resolve($request),
                'paginacion' => [
                    'pagina' => $paginador->currentPage(),
                    'por_pagina' => $paginador->perPage(),
                    'total' => $paginador->total(),
                    'ultima_pagina' => $paginador->lastPage(),
                ],
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[ScrambleResponse(
        status: 201,
        type: 'array{message:string,data:array{gestion:GestionCobranzaResource},errors:null,code:string}',
    )]
    public function store(GuardarGestionCobranzaRequest $request, Alumno $alumno): JsonResponse
    {
        $actor = $this->actor($request);
        $gestion = GestionCobranza::query()->create([
            ...$request->validated(),
            'colegio_id' => $actor->colegio_id,
            'alumno_id' => $alumno->id,
            'usuario_id' => $actor->id,
        ])->load('usuario:id,nombre,apellido');

        return response()->json([
            'message' => 'Gestión de cobranza registrada.',
            'data' => ['gestion' => (new GestionCobranzaResource($gestion))->resolve($request)],
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
