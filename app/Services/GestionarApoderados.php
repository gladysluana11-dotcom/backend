<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarApoderados
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearYVincular(Usuario $actor, Alumno $alumno, array $datos): Apoderado
    {
        return DB::transaction(function () use ($actor, $alumno, $datos): Apoderado {
            $alumno = $this->alumnoDelColegio($actor, $alumno);
            $apoderado = new Apoderado;
            $apoderado->fill([
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
            ]);
            $apoderado->forceFill(['colegio_id' => $actor->colegio_id])->save();
            $this->guardarVinculo($actor, $alumno, $apoderado, $datos, false);

            return $apoderado->load('alumnos');
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function vincular(
        Usuario $actor,
        Alumno $alumno,
        Apoderado $apoderado,
        array $datos,
    ): Apoderado {
        return DB::transaction(function () use ($actor, $alumno, $apoderado, $datos): Apoderado {
            $alumno = $this->alumnoDelColegio($actor, $alumno);
            $apoderado = $this->apoderadoDelColegio($actor, $apoderado);
            $existente = $alumno->apoderados()->whereKey($apoderado->id)->exists();
            $this->guardarVinculo($actor, $alumno, $apoderado, $datos, $existente);

            return $apoderado->load('alumnos');
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Usuario $actor, Apoderado $modelo, array $datos): Apoderado
    {
        $apoderado = $this->apoderadoDelColegio($actor, $modelo);
        $apoderado->fill($datos)->save();

        return $apoderado->refresh();
    }

    private function alumnoDelColegio(Usuario $actor, Alumno $alumno): Alumno
    {
        if ($alumno->colegio_id !== $actor->colegio_id) {
            throw ValidationException::withMessages([
                'alumno_id' => ['El alumno no pertenece al colegio del usuario autenticado.'],
            ]);
        }

        return $alumno;
    }

    private function apoderadoDelColegio(Usuario $actor, Apoderado $apoderado): Apoderado
    {
        if ($apoderado->colegio_id !== $actor->colegio_id) {
            throw ValidationException::withMessages([
                'apoderado_id' => ['El apoderado no pertenece al colegio del usuario autenticado.'],
            ]);
        }

        return $apoderado;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardarVinculo(
        Usuario $actor,
        Alumno $alumno,
        Apoderado $apoderado,
        array $datos,
        bool $existente,
    ): void {
        $vinculo = [
            'colegio_id' => $actor->colegio_id,
            'parentesco' => $datos['parentesco'],
            'telefono' => $datos['telefono'] ?? null,
            'email' => $datos['email'] ?? null,
            'responsable_pago' => $datos['responsable_pago'],
        ];

        if ($existente) {
            $alumno->apoderados()->updateExistingPivot($apoderado->id, $vinculo);
            $evento = 'apoderados.vinculo_actualizado';
        } else {
            $alumno->apoderados()->attach($apoderado->id, $vinculo);
            $evento = 'apoderados.vinculados';
        }

        app(RegistrarAuditoria::class)->registrar(
            $evento,
            'alumno_apoderado',
            $apoderado->id,
            valoresNuevos: [
                'alumno_id' => $alumno->id,
                'apoderado_id' => $apoderado->id,
                'parentesco' => $datos['parentesco'],
                'responsable_pago' => $datos['responsable_pago'],
            ],
            usuario: $actor,
            colegioId: $actor->colegio_id,
        );
    }
}
