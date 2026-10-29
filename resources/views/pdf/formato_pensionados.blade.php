<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pensionados - {{ $empleado->numempleado ?? $empleado->NUMEMPLEADO ?? '' }}</title>
    <style>
        @page { size: letter landscape; margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 10px; }
        h1 { text-align: center; color: #681b31; font-size: 17px; margin: 0 0 4px; }
        .subtitulo { text-align: center; color: #555; margin: 0 0 18px; }
        h2 { color: #681b31; font-size: 12px; margin: 15px 0 7px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #aaa; padding: 7px; vertical-align: top; }
        th { background: #681b31; color: #fff; text-align: left; }
        .datos-empleado td { width: 50%; }
        .etiqueta { font-weight: bold; color: #555; }
        .fecha { text-align: right; color: #666; font-size: 9px; margin-top: 18px; }
    </style>
</head>
<body>
    <h1>Relación de pensionados</h1>
    <p class="subtitulo">Información asociada al registro del empleado</p>

    <h2>Datos del empleado</h2>
    <table class="datos-empleado">
        <tr>
            <td><span class="etiqueta">Número de empleado:</span> {{ $empleado->numempleado ?? $empleado->NUMEMPLEADO ?? '—' }}</td>
            <td><span class="etiqueta">Nombre:</span> {{ $empleado->nombrecompleto ?? $empleado->NOMBRECOMPLETO ?? '—' }}</td>
        </tr>
        <tr>
            <td><span class="etiqueta">UR:</span> {{ $empleado->ur ?? $empleado->UR ?? '—' }}</td>
            <td><span class="etiqueta">Origen:</span> {{ $empleado->origen ?? $empleado->ORIGEN ?? '—' }}</td>
        </tr>
        <tr>
            <td><span class="etiqueta">Resumen:</span> {{ $empleado->pension ?? $empleado->PENSION ?? '—' }}</td>
            <td><span class="etiqueta">Pensionados registrados:</span> {{ $pensionados->count() }}</td>
        </tr>
    </table>

    <h2>Datos de pensionados</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre del pensionado</th>
                <th>CURP</th>
                <th>RFC</th>
                <th>Cuenta</th>
                <th>Porcentaje</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pensionados as $indice => $pensionado)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>{{ $pensionado->nombrepensionado ?? $pensionado->nombrePensionado ?? $pensionado->NOMBREPENSIONADO ?? '—' }}</td>
                    <td>{{ $pensionado->curp ?? $pensionado->CURP ?? '—' }}</td>
                    <td>{{ $pensionado->rfc ?? $pensionado->RFC ?? '—' }}</td>
                    <td>{{ $pensionado->cuenta ?? $pensionado->CUENTA ?? '—' }}</td>
                    <td>{{ $pensionado->porcentaje ?? $pensionado->PORCENTAJE ?? '—' }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="fecha">Generado el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>



