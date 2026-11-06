<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ficha de matrícula {{ $matricula->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #172033; }
        header { border-bottom: 2px solid #0f4c81; margin-bottom: 20px; padding-bottom: 12px; }
        header img { max-height: 65px; max-width: 150px; }
        h1 { color: #0f4c81; font-size: 19px; margin: 8px 0; }
        h2 { font-size: 13px; margin: 18px 0 8px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccd3dc; padding: 7px; text-align: left; }
        th { background: #f0f4f8; width: 32%; }
        footer { margin-top: 40px; }
        .firmas { margin-top: 65px; }
        .firma { border-top: 1px solid #333; display: inline-block; margin-right: 35px; padding-top: 6px; text-align: center; width: 210px; }
    </style>
</head>
<body>
    <header>
        @if ($logoData !== null)
            <img src="{{ $logoData }}" alt="Logo del colegio">
        @endif
        <h1>{{ $matricula->colegio->nombre }}</h1>
        <div>{{ $matricula->colegio->direccion }}</div>
        <div>{{ $matricula->colegio->telefono }} {{ $matricula->colegio->correo_contacto }}</div>
        <h1>Ficha de matrícula</h1>
    </header>

    <h2>Datos del alumno</h2>
    <table>
        <tr><th>Alumno</th><td>{{ $matricula->alumno->nombre }} {{ $matricula->alumno->apellido }}</td></tr>
        <tr><th>Documento</th><td>{{ $matricula->alumno->tipo_documento }} {{ $matricula->alumno->documento_identidad }}</td></tr>
        <tr><th>Fecha de nacimiento</th><td>{{ $matricula->alumno->fecha_nacimiento?->format('d/m/Y') }}</td></tr>
        <tr><th>Sexo</th><td>{{ $matricula->alumno->sexo }}</td></tr>
        <tr><th>Dirección</th><td>{{ $matricula->alumno->direccion }}</td></tr>
    </table>

    <h2>Datos de matrícula</h2>
    <table>
        <tr><th>Año escolar</th><td>{{ $matricula->anioEscolar?->nombre ?? $matricula->anio_escolar }}</td></tr>
        <tr><th>Grado</th><td>{{ $matricula->gradoRegistro?->nombre ?? $matricula->grado }}</td></tr>
        <tr><th>Sección</th><td>{{ $matricula->seccionRegistro?->nombre ?? $matricula->seccion }}</td></tr>
        <tr><th>Fecha</th><td>{{ $matricula->fecha_matricula->format('d/m/Y') }}</td></tr>
        <tr><th>Estado</th><td>{{ str_replace('_', ' ', ucfirst($matricula->estado)) }}</td></tr>
        <tr><th>Registrada por</th><td>{{ $matricula->usuarioRegistro?->nombre }} {{ $matricula->usuarioRegistro?->apellido }}</td></tr>
    </table>

    <h2>Documentos</h2>
    <table>
        <tr><th>Documento requerido</th><th>Estado</th></tr>
        @forelse ($matricula->documentos as $documento)
            <tr>
                <td>{{ $documento->documentoRequerido->nombre }}</td>
                <td>{{ ucfirst($documento->estado) }}</td>
            </tr>
        @empty
            <tr><td colspan="2">No hay documentos requeridos configurados.</td></tr>
        @endforelse
    </table>

    <footer>
        <div>Lima, {{ now()->format('d/m/Y') }}</div>
        <div class="firmas">
            <span class="firma">Firma del apoderado</span>
            <span class="firma">Firma y sello del colegio</span>
        </div>
    </footer>
</body>
</html>
