<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarDocumentoRequeridoRequest;
use App\Http\Resources\Api\V1\DocumentoRequeridoResource;
use App\Models\DocumentoRequerido;
use App\Models\Usuario;
use App\Services\GestionarMatriculas;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentoRequeridoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DocumentoRequerido::class);
        $documentos = DocumentoRequerido::query()
            ->where('colegio_id', $this->actor($request)->colegio_id)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'message' => 'Documentos requeridos del colegio.',
            'data' => ['documentos' => DocumentoRequeridoResource::collection($documentos)->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function store(
        GuardarDocumentoRequeridoRequest $request,
        GestionarMatriculas $gestion,
    ): JsonResponse {
        $documento = $gestion->guardarDocumentoRequerido($this->actor($request), null, $request->validated());

        return response()->json([
            'message' => 'Documento requerido creado.',
            'data' => ['documento' => (new DocumentoRequeridoResource($documento))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ], 201);
    }

    public function update(
        GuardarDocumentoRequeridoRequest $request,
        DocumentoRequerido $documento,
        GestionarMatriculas $gestion,
    ): JsonResponse {
        $actualizado = $gestion->guardarDocumentoRequerido(
            $this->actor($request),
            $documento,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Documento requerido actualizado.',
            'data' => ['documento' => (new DocumentoRequeridoResource($actualizado))->resolve($request)],
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
