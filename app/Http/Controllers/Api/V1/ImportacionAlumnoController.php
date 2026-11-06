<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmarImportacionAlumnosRequest;
use App\Http\Requests\ImportarAlumnosRequest;
use App\Models\Alumno;
use App\Models\Usuario;
use App\Services\GestionarAlumnos;
use App\Services\ImportarAlumnos;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ImportacionAlumnoController extends Controller
{
    public function plantilla(): BinaryFileResponse
    {
        Gate::authorize('import', Alumno::class);
        $ruta = tempnam(sys_get_temp_dir(), 'sge_alumnos_');

        if ($ruta === false) {
            throw new HttpException(500, 'No se pudo crear la plantilla de importación.');
        }

        $escritor = new Writer;
        $escritor->openToFile($ruta);
        $escritor->addRow(Row::fromValues([
            'nombres',
            'apellidos',
            'tipo_documento',
            'documento_identidad',
            'fecha_nacimiento',
            'sexo',
            'direccion',
        ]));
        $escritor->addRow(Row::fromValues([
            'Ejemplo ficticio',
            'Alumno de prueba',
            'DNI',
            '00000000',
            '2015-03-15',
            'F',
            'Dirección ficticia',
        ]));
        $escritor->close();

        return response()
            ->download($ruta, 'plantilla_alumnos.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function validar(
        ImportarAlumnosRequest $request,
        ImportarAlumnos $importaciones,
    ): JsonResponse {
        $resultado = $importaciones->validarYGuardar(
            $this->actor($request),
            $request->file('archivo'),
        );
        $valido = $resultado['errores'] === [];

        return response()->json([
            'message' => $valido
                ? 'Archivo validado. Confirma la importación antes de que venza el token.'
                : 'El archivo contiene errores.',
            'data' => $resultado,
            'errors' => $valido ? null : $resultado['errores'],
            'code' => $valido ? 'OK' : 'VALIDATION_ERROR',
        ], $valido ? 200 : 422);
    }

    public function confirmar(
        ConfirmarImportacionAlumnosRequest $request,
        ImportarAlumnos $importaciones,
        GestionarAlumnos $gestion,
    ): JsonResponse {
        $cantidad = $importaciones->confirmar(
            $this->actor($request),
            $request->string('token')->toString(),
            $gestion,
        );

        return response()->json([
            'message' => 'Alumnos importados.',
            'data' => ['alumnos_importados' => $cantidad],
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
