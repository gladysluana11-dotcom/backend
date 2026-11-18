<?php

namespace App\Listeners;

use App\Events\MatriculaConfirmada;
use App\Models\Usuario;
use App\Services\GenerarCuotas;
use App\Services\RegistrarAuditoria;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerarCuotasAlConfirmarMatricula implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private GenerarCuotas $generarCuotas,
        private RegistrarAuditoria $auditoria,
    ) {}

    public function handle(MatriculaConfirmada $event): void
    {
        $matricula = $event->matricula;
        $actor = Usuario::query()
            ->where('colegio_id', $matricula->colegio_id)
            ->find($matricula->usuario_id);

        $this->generarCuotas->generar($matricula, $actor);
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function failed(MatriculaConfirmada $event, Throwable $exception): void
    {
        $matricula = $event->matricula;
        $actor = Usuario::query()
            ->where('colegio_id', $matricula->colegio_id)
            ->find($matricula->usuario_id);

        Log::error('Falló la generación de cuotas de una matrícula confirmada.', [
            'matricula_id' => $matricula->id,
            'exception' => $exception::class,
        ]);
        $this->auditoria->registrar(
            'cuotas.generacion_fallida',
            'matriculas',
            (int) $matricula->id,
            valoresNuevos: ['exception' => class_basename($exception)],
            usuario: $actor,
            colegioId: (int) $matricula->colegio_id,
        );
    }
}
