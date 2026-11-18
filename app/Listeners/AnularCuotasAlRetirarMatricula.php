<?php

namespace App\Listeners;

use App\Events\MatriculaRetirada;
use App\Models\Usuario;
use App\Services\AnularCuotasPorRetiro;
use App\Services\RegistrarAuditoria;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnularCuotasAlRetirarMatricula implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private AnularCuotasPorRetiro $anularCuotas,
        private RegistrarAuditoria $auditoria,
    ) {}

    public function handle(MatriculaRetirada $event): void
    {
        $matricula = $event->matricula;
        $actor = Usuario::query()
            ->where('colegio_id', $matricula->colegio_id)
            ->find($matricula->usuario_id);

        $this->anularCuotas->anular($matricula, $actor);
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function failed(MatriculaRetirada $event, Throwable $exception): void
    {
        $matricula = $event->matricula;
        $actor = Usuario::query()
            ->where('colegio_id', $matricula->colegio_id)
            ->find($matricula->usuario_id);

        Log::error('Falló la anulación de cuotas de una matrícula retirada.', [
            'matricula_id' => $matricula->id,
            'exception' => $exception::class,
        ]);
        $this->auditoria->registrar(
            'cuotas.anulacion_retiro_fallida',
            'matriculas',
            (int) $matricula->id,
            valoresNuevos: ['exception' => class_basename($exception)],
            usuario: $actor,
            colegioId: (int) $matricula->colegio_id,
        );
    }
}
