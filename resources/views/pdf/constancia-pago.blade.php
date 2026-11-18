<div>
    <!-- The best way to take care of the future is to take care of the present moment. - Thich Nhat Hanh -->
</div>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia {{ $pago->constancia }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1, h2 { margin-bottom: 6px; }
        .encabezado { border-bottom: 1px solid #d1d5db; padding-bottom: 12px; }
        .anulado { color: #b91c1c; font-size: 20px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; }
        .monto { text-align: right; }
    </style>
</head>
<body>
    <div class="encabezado">
        @if ($logoData !== null)
            <img src="{{ $logoData }}" alt="Logo" style="max-width: 100px; max-height: 70px;">
        @endif
        <h1>{{ $pago->colegio->nombre }}</h1>
        <div>{{ $pago->colegio->direccion }}</div>
        <div>{{ $pago->colegio->telefono }} · {{ $pago->colegio->correo_contacto }}</div>
    </div>
    @if ($pago->estado === 'anulado')
        <p class="anulado">ANULADO</p>
    @endif
    <h2>Constancia de pago {{ $pago->constancia }}</h2>
    <p>Alumno: {{ $pago->alumno->nombre }} {{ $pago->alumno->apellido }}</p>
    <p>Fecha: {{ $pago->fecha_pago->format('d/m/Y') }}</p>
    <p>Medio: {{ $pago->medio }}</p>
    <p>Operación: {{ $pago->numero_operacion ?? 'No aplica' }}</p>
    <p>Monto recibido: S/ {{ number_format((float) $pago->monto, 2) }}</p>
    <table>
        <thead>
            <tr><th>Cuota</th><th>Vencimiento</th><th class="monto">Monto aplicado (S/)</th></tr>
        </thead>
        <tbody>
            @foreach ($pago->aplicaciones as $aplicacion)
                <tr>
                    <td>{{ $aplicacion->cuota->descripcion }}</td>
                    <td>{{ $aplicacion->cuota->vencimiento->format('d/m/Y') }}</td>
                    <td class="monto">{{ number_format((float) $aplicacion->monto_aplicado, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
