<?php

namespace App\Services;

use App\Models\AnioEscolar;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\ImportacionAcademica as Importacion;
use App\Models\Nivel;
use App\Models\Seccion;
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

class ImportarEstructuraAcademica
{
    private const array ENCABEZADOS = ['nivel', 'grado', 'seccion', 'capacidad', 'curso'];

    /**
     * @return array{token: ?string, expira_en: ?string, filas_validas: int, errores: list<array{fila: int, campo: string, mensaje: string}>}
     */
    public function validarYGuardar(Usuario $actor, int $anioEscolarId, UploadedFile $archivo): array
    {
        $filas = $this->leerFilas($archivo->getRealPath());
        $errores = $this->validarFilas($filas);

        if ($errores !== []) {
            return [
                'token' => null,
                'expira_en' => null,
                'filas_validas' => count($filas),
                'errores' => $errores,
            ];
        }

        $this->limpiarVencidas($actor);
        $anio = AnioEscolar::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('cerrado', false)
            ->findOrFail($anioEscolarId);
        $token = bin2hex(random_bytes(32));
        $extension = mb_strtolower($archivo->getClientOriginalExtension());
        $ruta = $archivo->storeAs('importaciones/academicas', Str::uuid().'.'.$extension, 'local');

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
            'anio_escolar_id' => $anio->id,
            'token_hash' => hash('sha256', $token),
            'ruta_archivo' => $ruta,
            'sha256' => $hash,
            'expira_en' => $expiraEn,
        ]);

        return [
            'token' => $token,
            'expira_en' => $expiraEn->toIso8601String(),
            'filas_validas' => $this->contarFilasConDatos($filas),
            'errores' => [],
        ];
    }

    /**
     * @return array{niveles: int, grados: int, secciones: int, cursos: int}
     */
    public function confirmar(Usuario $actor, string $token): array
    {
        [$conteos, $ruta, $importacionId] = DB::transaction(function () use ($actor, $token): array {
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

            if ($this->validarFilas($filas) !== []) {
                throw ValidationException::withMessages([
                    'archivo' => ['El archivo almacenado ya no supera la validación.'],
                ]);
            }

            $anio = AnioEscolar::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($importacion->anio_escolar_id);

            if ($anio->cerrado) {
                throw ValidationException::withMessages([
                    'anio_escolar_id' => ['No se puede importar estructura en un año escolar cerrado.'],
                ]);
            }

            $conteos = $this->guardarFilas($actor, $anio, $filas);
            $importacion->forceFill(['confirmada_en' => now()])->save();

            return [$conteos, $importacion->ruta_archivo, $importacion->id];
        }, attempts: 5);

        if (! Storage::disk('local')->delete($ruta)) {
            logger()->warning('No se pudo borrar el archivo de importación confirmado.', [
                'colegio_id' => $actor->colegio_id,
                'importacion_id' => $importacionId,
            ]);
        }

        return $conteos;
    }

    /**
     * @param  list<list<mixed>>  $filas
     * @return list<array{fila: int, campo: string, mensaje: string}>
     */
    private function validarFilas(array $filas): array
    {
        if (count($filas) < 2) {
            return [[
                'fila' => 1,
                'campo' => 'archivo',
                'mensaje' => 'El archivo debe incluir encabezados y al menos una fila.',
            ]];
        }

        if (count($filas) > 1001) {
            return [[
                'fila' => 1002,
                'campo' => 'archivo',
                'mensaje' => 'El archivo no puede superar 1000 filas de datos.',
            ]];
        }

        $encabezados = array_map(fn (mixed $valor): string => Str::slug((string) $valor, '_'), $filas[0]);

        if ($encabezados !== self::ENCABEZADOS) {
            return [[
                'fila' => 1,
                'campo' => 'archivo',
                'mensaje' => 'Los encabezados deben ser Nivel, Grado, Sección, Capacidad y Curso, en ese orden.',
            ]];
        }

        $errores = [];
        $cursosVistos = [];
        $filasConDatos = 0;

        foreach (array_slice($filas, 1, null, true) as $indice => $valores) {
            if (count(array_filter($valores, fn (mixed $valor): bool => trim((string) $valor) !== '')) === 0) {
                continue;
            }

            $filasConDatos++;
            $numeroFila = $indice + 1;
            $datos = array_combine(self::ENCABEZADOS, array_pad(array_slice($valores, 0, 5), 5, null));
            $datos = array_map(
                fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor,
                $datos,
            );
            $validador = Validator::make($datos, [
                'nivel' => ['required', 'string', 'max:100'],
                'grado' => ['required', 'string', 'max:100'],
                'seccion' => ['required', 'string', 'max:50'],
                'capacidad' => ['required', 'integer', 'min:1', 'max:500'],
                'curso' => ['required', 'string', 'max:100'],
            ]);

            foreach ($validador->errors()->messages() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $errores[] = ['fila' => $numeroFila, 'campo' => $campo, 'mensaje' => $mensaje];
                }
            }

            if ($validador->fails()) {
                continue;
            }

            $claveCurso = implode('|', array_map(
                fn (string $valor): string => mb_strtolower(trim($valor)),
                [$datos['nivel'], $datos['grado'], $datos['curso']],
            ));

            if (isset($cursosVistos[$claveCurso])) {
                $errores[] = [
                    'fila' => $numeroFila,
                    'campo' => 'curso',
                    'mensaje' => 'El curso está duplicado para el mismo nivel y grado en el archivo.',
                ];
            }

            $cursosVistos[$claveCurso] = true;
        }

        if ($errores === [] && $filasConDatos === 0) {
            $errores[] = ['fila' => 2, 'campo' => 'archivo', 'mensaje' => 'El archivo no contiene filas de datos.'];
        }

        return $errores;
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

    /**
     * @param  list<list<mixed>>  $filas
     */
    private function contarFilasConDatos(array $filas): int
    {
        return count(array_filter(
            array_slice($filas, 1),
            fn (array $valores): bool => count(array_filter(
                $valores,
                fn (mixed $valor): bool => trim((string) $valor) !== '',
            )) > 0,
        ));
    }

    private function limpiarVencidas(Usuario $actor): void
    {
        $vencidas = Importacion::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('expira_en', '<=', now())
            ->get();

        foreach ($vencidas as $importacion) {
            $disco = Storage::disk('local');

            if (! $disco->delete($importacion->ruta_archivo) && $disco->exists($importacion->ruta_archivo)) {
                throw new \RuntimeException('No se pudo eliminar un archivo de importación vencido.');
            }

            $importacion->delete();
        }
    }

    /**
     * @param  list<list<mixed>>  $filas
     * @return array{niveles: int, grados: int, secciones: int, cursos: int}
     */
    private function guardarFilas(Usuario $actor, AnioEscolar $anio, array $filas): array
    {
        $conteos = ['niveles' => 0, 'grados' => 0, 'secciones' => 0, 'cursos' => 0];
        $siguienteOrdenNivel = (int) (Nivel::query()
            ->where('colegio_id', $actor->colegio_id)
            ->max('orden') ?? 0);
        $niveles = [];
        $grados = [];
        $secciones = [];
        $cursos = [];

        foreach (array_slice($filas, 1) as $valores) {
            if (count(array_filter($valores, fn (mixed $valor): bool => trim((string) $valor) !== '')) === 0) {
                continue;
            }

            $datos = array_combine(self::ENCABEZADOS, array_pad(array_slice($valores, 0, 5), 5, null));
            $nombreNivel = trim((string) $datos['nivel']);
            $nombreGrado = trim((string) $datos['grado']);
            $nombreSeccion = trim((string) $datos['seccion']);
            $nombreCurso = trim((string) $datos['curso']);
            $capacidad = (int) $datos['capacidad'];
            $claveNivel = mb_strtolower($nombreNivel);

            if (! isset($niveles[$claveNivel])) {
                $nivel = Nivel::query()->firstOrCreate(
                    ['colegio_id' => $actor->colegio_id, 'nombre' => $nombreNivel],
                    ['orden' => ++$siguienteOrdenNivel],
                );
                $niveles[$claveNivel] = $nivel;
                $conteos['niveles'] += (int) $nivel->wasRecentlyCreated;
            }

            $nivel = $niveles[$claveNivel];
            $claveGrado = $nivel->id.'|'.mb_strtolower($nombreGrado);

            if (! isset($grados[$claveGrado])) {
                $siguienteOrdenGrado = (int) (Grado::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->where('nivel_id', $nivel->id)
                    ->max('orden') ?? 0);
                $grado = Grado::query()->firstOrCreate(
                    [
                        'colegio_id' => $actor->colegio_id,
                        'nivel_id' => $nivel->id,
                        'nombre' => $nombreGrado,
                    ],
                    ['orden' => $siguienteOrdenGrado + 1],
                );
                $grados[$claveGrado] = $grado;
                $conteos['grados'] += (int) $grado->wasRecentlyCreated;
            }

            $grado = $grados[$claveGrado];
            $claveSeccion = $anio->id.'|'.$grado->id.'|'.mb_strtolower($nombreSeccion);

            if (! isset($secciones[$claveSeccion])) {
                $seccion = Seccion::query()->firstOrCreate(
                    [
                        'colegio_id' => $actor->colegio_id,
                        'anio_escolar_id' => $anio->id,
                        'grado_id' => $grado->id,
                        'nombre' => $nombreSeccion,
                    ],
                    ['capacidad' => $capacidad, 'activo' => true],
                );

                if (! $seccion->wasRecentlyCreated && ($seccion->capacidad !== $capacidad || ! $seccion->activo)) {
                    $seccion->forceFill(['capacidad' => $capacidad, 'activo' => true])->save();
                }

                $secciones[$claveSeccion] = $seccion;
                $conteos['secciones'] += (int) $seccion->wasRecentlyCreated;
            }

            $claveCurso = $nivel->id.'|'.$grado->id.'|'.mb_strtolower($nombreCurso);

            if (! isset($cursos[$claveCurso])) {
                $curso = Curso::query()->firstOrCreate(
                    [
                        'colegio_id' => $actor->colegio_id,
                        'nivel_id' => $nivel->id,
                        'grado_id' => $grado->id,
                        'nombre' => $nombreCurso,
                    ],
                    ['activo' => true],
                );

                if (! $curso->wasRecentlyCreated && ! $curso->activo) {
                    $curso->forceFill(['activo' => true])->save();
                }

                $cursos[$claveCurso] = $curso;
                $conteos['cursos'] += (int) $curso->wasRecentlyCreated;
            }
        }

        return $conteos;
    }
}
