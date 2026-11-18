<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GestionarVoucherPago
{
    public function __construct(private RegistrarAuditoria $auditoria) {}

    public function adjuntar(Usuario $actor, Pago $pago, UploadedFile $voucher): Pago
    {
        if (! in_array($pago->medio, ['deposito', 'transferencia'], true)) {
            throw ValidationException::withMessages([
                'voucher' => 'Solo se pueden adjuntar vouchers a pagos por depósito o transferencia.',
            ]);
        }

        $ruta = $voucher->store('vouchers/'.$actor->colegio_id.'/'.$pago->id, 'local');

        if (! is_string($ruta) || $ruta === '') {
            throw new RuntimeException('No se pudo guardar el voucher en el almacenamiento privado.');
        }

        $rutaAnterior = $pago->voucher_path;

        try {
            DB::transaction(function () use ($pago, $ruta): void {
                Pago::query()
                    ->where('colegio_id', $pago->colegio_id)
                    ->whereKey($pago->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->forceFill(['voucher_path' => $ruta])
                    ->save();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($ruta);

            throw $exception;
        }

        if ($rutaAnterior !== null) {
            Storage::disk('local')->delete($rutaAnterior);
        }

        $this->auditoria->registrar(
            'pagos.voucher_adjuntado',
            'pagos',
            (int) $pago->id,
            valoresNuevos: ['tipo' => $voucher->getMimeType(), 'tamano_bytes' => $voucher->getSize()],
            usuario: $actor,
            colegioId: (int) $actor->colegio_id,
        );

        return $pago->refresh();
    }

    public function descargar(Pago $pago): StreamedResponse
    {
        $ruta = $pago->voucher_path;

        abort_if($ruta === null || ! Storage::disk('local')->exists($ruta), 404);
        $mime = Storage::disk('local')->mimeType($ruta);

        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true), 404);

        return Storage::disk('local')->download($ruta, basename($ruta), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
