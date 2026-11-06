<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\ImportacionAlumno as Importacion;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class ImportarAlumnos
{
    private const array ENCABEZADOS = [
        'nombres',
        'apellidos',
        'tipo_documento',
        'documento_identidad',
        'fecha_nacimiento',
        'sexo',
        'direccion',
    ];

    /**
     * @return array{token: ?string, expira_en: ?string, filas_validas: int, errores: list<array{fila: int, campo: string, mensaje: string}>}
     */
    public function validarYGuardar(Usuario $actor, UploadedFile $archivo): array
    {
        $rutaTemporal = $archivo->getRealPath();
        $filas = $this->leerFilas($rutaTemporal);
        $errores = $this->validarFilas($actor, $filas);
        $cantidadFilas = $this->contarFilasConDatos($filas);

        if ($errores !== []) {
            return [
                'token' => null,
                'expira_en' => null,
                'filas_validas' => 0,
                'errores' => $errores,
            ];
        }

        $this->limpiarVencidas($actor);
        $token = bin2hex(random_bytes(32));
        $extension = mb_strtolower($archivo->getClientOriginalExtension());
        $ruta = $archivo->storeAs('importaciones/alumnos', Str::uuid().'.'.$extension, 'local');

        if ($ruta === false) {
            throw ValidationException::withMessages([
                'archivo' => ['No se pudo guardar el archivo validado. Intenta nuevamente.'],
            ]);
        }

        $hash = hash_file('sha256', Storage::disk('local')->path($ruta));

        if ($hash === false) {
            throw ValidationException::withMessages([
                'archivo' => ['No se pudo verificar el archivo validado. Intenta nuevamente.'],
            ]);
        }

        $expiraEn = now()->addMinutes(15);
        Importacion::query()->create([
            'colegio_id' => $actor->colegio_id,
            'usuario_id' => $actor->id,
            'token_hash' => hash('sha256', $token),
            'ruta_archivo' => $ruta,
            'sha256' => $hash,
            'expira_en' => $expiraEn,
        ]);

        return [
            'token' => $token,
            'expira_en' => $expiraEn->toIso8601String(),
            'filas_validas' => $cantidadFilas,
            'errores' => [],
        ];
    }

    public function confirmar(Usuario $actor, string $token, GestionarAlumnos $gestion): int
    {
        [$cantidad, $ruta, $importacionId] = DB::transaction(function () use ($actor, $token, $gestion): array {
            $importacion = Importacion::query()
                ->where('colegio_id', $actor->colegio_id)
                ->where('usuario_id', $actor->id)
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->firstOrFail();

            if ($importacion->confirmada_en !== null || Carbon::parse($importacion->expira_en)->isPast()) {
                throw ValidationException::withMessages([
                    'token' => ['El token de importación expiró o ya fue utilizado.'],
                ]);
            }

            $rutaAbsoluta = Storage::disk('local')->path($importacion->ruta_archivo);
            $hash = is_file($rutaAbsoluta) ? hash_file('sha256', $rutaAbsoluta) : false;

            if ($hash === false || ! hash_equals($importacion->sha256, $hash)) {
                throw ValidationException::withMessages([
                    'token' => ['El archivo validado ya no está disponible o fue modificado.'],
                ]);
            }

            $filas = $this->leerFilas($rutaAbsoluta);
            $errores = $this->validarFilas($actor, $filas);

            if ($errores !== []) {
                throw ValidationException::withMessages([
                    'archivo' => ['El archivo validado ya no supera la validación. Vuelve a cargarlo.'],
                ]);
            }

            $datos = $this->normalizarFilas($filas);

            foreach ($datos as $fila) {
                $gestion->crear($actor, $fila);
            }

            $importacion->forceFill(['confirmada_en' => now()])->save();

            return [count($datos), $importacion->ruta_archivo, $importacion->id];
        }, attempts: 5);

        if (! Storage::disk('local')->delete($ruta) && Storage::disk('local')->exists($ruta)) {
            logger()->warning('No se pudo borrar el archivo de importación de alumnos confirmado.', [
                'colegio_id' => $actor->colegio_id,
                'importacion_id' => $importacionId,
            ]);
        }

        return $cantidad;
    }

    /**
     * @param  list<list<mixed>>  $filas
     * @return list<array{fila: int, campo: string, mensaje: string}>
     */
    private function validarFilas(Usuario $actor, array $filas): array
    {
        if (count($filas) < 2) {
            return [['fila' => 1, 'campo' => 'archivo', 'mensaje' => 'Incluye encabezados y al menos una fila de datos.']];
        }

        if (count($filas) > 1001) {
            return [['fila' => 1002, 'campo' => 'archivo', 'mensaje' => 'El archivo no puede superar 1000 filas de datos.']];
        }

        $encabezados = array_map(fn (mixed $valor): string => Str::slug((string) $valor, '_'), $filas[0]);

        if ($encabezados !== self::ENCABEZADOS) {
            return [[
                'fila' => 1,
                'campo' => 'archivo',
                'mensaje' => 'Los encabezados deben coincidir con la plantilla y conservar su orden.',
            ]];
        }

        $errores = [];
        $documentosVistos = [];
        $documentosEntrantes = [];

        foreach (array_slice($filas, 1, 1000) as $valores) {
            if (! $this->filaVacia($valores)) {
                $documento = trim((string) $this->datosFila($valores)['documento_identidad']);

                if ($documento !== '') {
                    $documentosEntrantes[] = $documento;
                }
            }
        }

        $documentosExistentes = Alumno::query()
            ->where('colegio_id', $actor->colegio_id)
            ->whereIn('documento_identidad', array_values(array_unique($documentosEntrantes)))
            ->pluck('documento_identidad')
            ->filter(static fn (mixed $documento): bool => is_string($documento) && trim($documento) !== '')
            ->map(static fn (string $documento): string => mb_strtolower(trim($documento)))
            ->all();
        $existentes = array_fill_keys($documentosExistentes, true);

        foreach (array_slice($filas, 1, 1000, true) as $indice => $valores) {
            if ($this->filaVacia($valores)) {
                continue;
            }

            $fila = $this->datosFila($valores);
            $numeroFila = (int) $indice + 1;
            $validador = Validator::make($fila, [
                'nombre' => ['required', 'string', 'max:255'],
                'apellido' => ['required', 'string', 'max:255'],
                'tipo_documento' => ['required', 'string', 'max:30'],
                'documento_identidad' => ['required', 'string', 'max:30'],
                'fecha_nacimiento' => ['required', 'date', 'before_or_equal:today'],
                'sexo' => ['required', 'string', 'max:20'],
                'direccion' => ['required', 'string', 'max:255'],
            ]);

            foreach ($validador->errors()->messages() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $errores[] = [
                        'fila' => $numeroFila,
                        'campo' => $campo,
                        'mensaje' => $mensaje,
                    ];
                }
            }

            $documento = mb_strtolower(trim((string) $fila['documento_identidad']));

            if ($documento === '') {
                continue;
            }

            if (isset($documentosVistos[$documento]) || isset($existentes[$documento])) {
                $errores[] = [
                    'fila' => $numeroFila,
                    'campo' => 'documento_identidad',
                    'mensaje' => 'El documento está duplicado en el archivo o ya existe en el colegio.',
                ];
            }

            $documentosVistos[$documento] = true;
        }

        return $errores;
    }

    /**
     * @param  list<list<mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function normalizarFilas(array $filas): array
    {
        $datos = [];

        foreach (array_slice($filas, 1) as $valores) {
            if (! $this->filaVacia($valores)) {
                $datos[] = $this->datosFila($valores);
            }
        }

        return $datos;
    }

    /**
     * @param  list<mixed>  $valores
     * @return array<string, mixed>
     */
    private function datosFila(array $valores): array
    {
        $fila = array_combine(self::ENCABEZADOS, array_pad(array_slice($valores, 0, count(self::ENCABEZADOS)), count(self::ENCABEZADOS), null));
        $fecha = $fila['fecha_nacimiento'];

        if ($fecha instanceof \DateTimeInterface) {
            $fecha = $fecha->format('Y-m-d');
        } elseif (is_numeric($fecha) && (float) $fecha > 0) {
            $fecha = Carbon::create(1899, 12, 30)->addDays((int) $fecha)->toDateString();
        }

        return [
            'nombre' => trim((string) $fila['nombres']),
            'apellido' => trim((string) $fila['apellidos']),
            'tipo_documento' => trim((string) $fila['tipo_documento']),
            'documento_identidad' => trim((string) $fila['documento_identidad']),
            'fecha_nacimiento' => trim((string) $fecha),
            'sexo' => trim((string) $fila['sexo']),
            'direccion' => trim((string) $fila['direccion']),
        ];
    }

    /**
     * @param  list<mixed>  $valores
     */
    private function filaVacia(array $valores): bool
    {
        foreach ($valores as $valor) {
            if ($valor instanceof \DateTimeInterface || trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<list<mixed>>  $filas
     */
    private function contarFilasConDatos(array $filas): int
    {
        return count(array_filter(
            array_slice($filas, 1),
            fn (array $valores): bool => ! $this->filaVacia($valores),
        ));
    }

    /**
     * @return list<list<mixed>>
     */
    private function leerFilas(string|false $ruta): array
    {
        if ($ruta === false) {
            throw ValidationException::withMessages([
                'archivo' => ['No se pudo leer el archivo enviado.'],
            ]);
        }

        try {
            $lector = ReaderFactory::createFromFileByMimeType($ruta);
            $lector->open($ruta);
        } catch (IOException|UnsupportedTypeException) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo no tiene un formato Excel o CSV válido.'],
            ]);
        }

        $filas = [];

        try {
            foreach ($lector->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $fila) {
                    $filas[] = $fila->toArray();

                    if (count($filas) > 1001) {
                        return $filas;
                    }
                }

                break;
            }
        } finally {
            $lector->close();
        }

        return $filas;
    }

    private function limpiarVencidas(Usuario $actor): void
    {
        $vencidas = Importacion::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('usuario_id', $actor->id)
            ->where('expira_en', '<=', now())
            ->get();

        foreach ($vencidas as $importacion) {
            $disco = Storage::disk('local');

            if (! $disco->delete($importacion->ruta_archivo) && $disco->exists($importacion->ruta_archivo)) {
                throw new \RuntimeException('No se pudo eliminar un archivo de importación de alumnos vencido.');
            }

            $importacion->delete();
        }
    }
}
