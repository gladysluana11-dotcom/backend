<div>
    <!-- No surplus words or unnecessary actions. - Marcus Aurelius -->
</div>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        .totales { margin-top: 16px; }
    </style>
</head>
<body>
    @if ($logoData !== null)
        <img src="{{ $logoData }}" alt="Logo" style="max-width: 100px; max-height: 70px;">
    @endif
    <h1>{{ $alumno['nombre'] }} {{ $alumno['apellido'] }}</h1>
    <h2>Estado de cuenta</h2>
    <table>
        <thead>
            <tr><th>Concepto</th><th>Vencimiento</th><th>Estado</th><th>Descuentos (S/)</th><th>Saldo (S/)</th></tr>
        </thead>
        <tbody>
            @foreach ($cuotas as $cuota)
                <tr>
                    <td>{{ $cuota['descripcion'] }}</td>
                    <td>{{ $cuota['vencimiento'] }}</td>
                    <td>{{ $cuota['estado'] }}</td>
                    <td>{{ $cuota['total_descuentos'] }}</td>
                    <td>{{ $cuota['saldo'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="totales">
        <p>Emitido: S/ {{ $totales['emitido'] }}</p>
        <p>Pagado: S/ {{ $totales['pagado'] }}</p>
        <p>Saldo: S/ {{ $totales['saldo'] }}</p>
        <p>Vencido: S/ {{ $totales['vencido'] }}</p>
    </div>
</body>
</html>
