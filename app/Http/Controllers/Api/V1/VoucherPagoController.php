<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjuntarVoucherPagoRequest;
use App\Http\Resources\Api\V1\PagoResource;
use App\Models\Pago;
use App\Models\Usuario;
use App\Services\GestionarVoucherPago;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoucherPagoController extends Controller
{
    #[ScrambleResponse(status: 200, type: 'array{message:string,data:array{pago:PagoResource},errors:null,code:string}')]
    public function store(
        AdjuntarVoucherPagoRequest $request,
        Pago $pago,
        GestionarVoucherPago $gestion,
    ): JsonResponse {
        $usuario = $request->user();
        $archivo = $request->file('voucher');

        if (! $usuario instanceof Usuario) {
            throw new AuthenticationException;
        }

        if (! $archivo instanceof UploadedFile) {
            throw ValidationException::withMessages(['voucher' => 'Adjunta un voucher válido.']);
        }

        $actualizado = $gestion->adjuntar($usuario, $pago, $archivo);

        return response()->json([
            'message' => 'Voucher adjuntado al pago.',
            'data' => ['pago' => (new PagoResource($actualizado))->resolve($request)],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[ScrambleResponse(
        status: 200,
        description: 'Voucher almacenado de forma privada.',
        mediaType: 'application/octet-stream',
        type: 'string',
        format: 'binary',
    )]
    public function show(Pago $pago, GestionarVoucherPago $gestion): StreamedResponse
    {
        Gate::authorize('view', $pago);

        return $gestion->descargar($pago);
    }
}
