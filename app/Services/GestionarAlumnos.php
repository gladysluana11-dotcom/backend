<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\HistorialEstadoAlumno;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GestionarAlumnos
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(Usuario $actor, array $datos): Alumno
    {
        return DB::transaction(function () use ($actor, $datos): Alumno {
            $alumno = new Alumno;
            $alumno->fill([
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'tipo_documento' => $datos['tipo_documento'],
                'documento_identidad' => $datos['documento_identidad'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'sexo' => $datos['sexo'],
                'direccion' => $datos['direccion'],
            ]);
            $alumno->forceFill([
                'colegio_id' => $actor->colegio_id,
                'estado' => 'activo',
                'activo' => true,
            ])->save();

            HistorialEstadoAlumno::query()->create([
                'colegio_id' => $actor->colegio_id,
                'alumno_id' => $alumno->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'activo',
                'fecha' => now(),
                'usuario_id' => $actor->id,
            ]);

            return $alumno->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Usuario $actor, Alumno $modelo, array $datos): Alumno
    {
        return DB::transaction(function () use ($actor, $modelo, $datos): Alumno {
            $alumno = Alumno::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($modelo->id);
            $estadoAnterior = $alumno->estado;
            $estadoNuevo = $datos['estado'] ?? $estadoAnterior;
            $motivo = $datos['motivo'] ?? null;
            unset($datos['motivo']);

            $alumno->fill($datos);

            if ($estadoNuevo !== $estadoAnterior) {
                $alumno->forceFill([
                    'estado' => $estadoNuevo,
                    'activo' => $estadoNuevo === 'activo',
                ]);
            }

            $alumno->save();

            if ($estadoNuevo !== $estadoAnterior) {
                HistorialEstadoAlumno::query()->create([
                    'colegio_id' => $actor->colegio_id,
                    'alumno_id' => $alumno->id,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => $estadoNuevo,
                    'motivo' => $motivo,
                    'fecha' => now(),
                    'usuario_id' => $actor->id,
                ]);
            }

            return $alumno->refresh();
        });
    }

    public function guardarFoto(Usuario $actor, Alumno $modelo, UploadedFile $foto): Alumno
    {
        if (! in_array($foto->getMimeType(), ['image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages([
                'foto' => ['La foto debe ser un archivo JPG o PNG válido.'],
            ]);
        }

        $alumno = Alumno::query()
            ->where('colegio_id', $actor->colegio_id)
            ->findOrFail($modelo->id);
        $rutaAnterior = $alumno->foto_path;
        $rutaNueva = $foto->store('alumnos/fotos', 'local');

        if ($rutaNueva === false) {
            throw ValidationException::withMessages([
                'foto' => ['No se pudo guardar la foto del alumno. Intenta nuevamente.'],
            ]);
        }

        $alumno->forceFill(['foto_path' => $rutaNueva])->save();

        if ($rutaAnterior !== null && ! Storage::disk('local')->delete($rutaAnterior)) {
            logger()->warning('No se pudo retirar la foto anterior del alumno.', [
                'colegio_id' => $actor->colegio_id,
                'alumno_id' => $alumno->id,
            ]);
        }

        return $alumno->refresh();
    }
}
