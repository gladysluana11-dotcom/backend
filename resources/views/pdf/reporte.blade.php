<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 5px; }
        h2 { font-size: 12px; margin: 14px 0 5px; }
        .encabezado { border-bottom: 1px solid #9ca3af; padding-bottom: 8px; }
        .logo { max-height: 48px; max-width: 150px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #d1d5db; padding: 4px; text-align: left; }
        th { background: #f3f4f6; }
        .filtros td:first-child, .resumen td:first-child { font-weight: bold; width: 25%; }
    </style>
</head>
<body>
    <div class="encabezado">
        @if ($logoData !== null)
            <img class="logo" src="{{ $logoData }}" alt="Logo del colegio">
        @endif
        <h1>{{ $titulo }}</h1>
        <div>{{ $colegio }}</div>
    </div>

    @if ($resumen !== [])
        <h2>Resumen</h2>
        <table class="resumen">
            @foreach ($resumen as $campo => $valor)
                <tr>
                    <td>{{ str($campo)->replace('_', ' ')->headline() }}</td>
                    <td>{{ is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : $valor }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($filtros !== [])
        <h2>Filtros aplicados</h2>
        <table class="filtros">
            @foreach ($filtros as $campo => $valor)
                <tr>
                    <td>{{ str($campo)->replace('_', ' ')->headline() }}</td>
                    <td>{{ is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : $valor }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Detalle</h2>
    <table>
        <thead>
            <tr>
                @foreach ($columnas as $etiqueta)
                    <th>{{ $etiqueta }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach (array_keys($columnas) as $campo)
                        @php($valor = $fila[$campo] ?? '')
                        <td>{{ is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : $valor }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(1, count($columnas)) }}">Sin resultados para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
