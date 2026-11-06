<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarDocumentoMatriculaRequest;
use App\Http\Resources\Api\V1\MatriculaDocumentoResource;
use App\Models\MatriculaDocumento;
use App\Models\Usuario;
use App\Services\GestionarMatriculas;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatriculaDocumentoController extends Controller
{
    public function update(
        ActualizarDocumentoMatriculaRequest $request,
        MatriculaDocumento $documento,
        GestionarMatriculas $gestion,
    ): JsonResponse {
        $actualizado = $gestion->actualizarDocumento(
            $this->actor($request),
            $documento,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Estado del documento de matrícula actualizado.',
            'data' => ['documento' => (new MatriculaDocumentoResource($actualizado))->resolve($request)],
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
