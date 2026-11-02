<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formato de Recepción</title>
    <style>
        @page {
            size: letter portrait;
            margin: 12mm 15mm 12mm 15mm;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #000; line-height: 1.4; margin: 0; padding: 0; background-color: #fff; }
        .page-container { width: 100%; height: 100%; page-break-after: avoid; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { vertical-align: top; border: none; padding: 0; text-align: right; }
        .institution-title { font-size: 11px; font-weight: bold; line-height: 1.3; color: #000; text-transform: uppercase; }
        .title-block { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .title-main { background-color: #b8860b; color: #fff; font-weight: bold; font-size: 13px; text-align: center; padding: 10px; text-transform: uppercase; border: 1px solid #7f5e06; }
        .title-sub { background-color: #dcdcdc; color: #000; font-weight: bold; font-size: 11px; text-align: center; padding: 10px; text-transform: uppercase; border: 1px solid #888; border-top: none; }
        table.doc-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 20px; }
        table.doc-table th, table.doc-table td { border: 1px solid #555; padding: 8px 10px; font-size: 10.5px; vertical-align: middle; word-wrap: break-word; }
        .bg-section-header { background-color: #808080; color: #fff; font-weight: bold; text-transform: uppercase; text-align: center; font-size: 11px; padding: 8px !important; }
        .bg-label { background-color: #dcdcdc; font-weight: bold; text-transform: uppercase; text-align: center; font-size: 10px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .box-signature { height: 120px; vertical-align: bottom !important; padding-bottom: 12px !important; text-align: center; font-weight: bold; font-size: 10px; }
        .legal-constancy { font-size: 9px; text-align: center; font-weight: bold; line-height: 1.3; padding: 10px !important; background-color: #e8e8e8; }
        .digit-cell { width: 25px; text-align: center; font-weight: bold; font-size: 12px; font-family: 'Courier New', Courier, monospace; }
        .meta-info { font-size: 8.5px; text-align: right; margin-top: 10px; color: #444; font-family: monospace; }
    </style>
</head>
<body>

@php
    $nombre = $empleado->NOMBRECOMPLETO 
        ?? $empleado->nombrecompleto 
        ?? trim(($empleado->NOMEMPLEADO ?? '') . ' ' . ($empleado->APPEMPLEADO ?? ''));
    
    $nombreCompleto = strtoupper($nombre ?: '-');
    $numeroEmpleado = $empleado->NUMEMPLEADO ?? $empleado->numempleado ?? '-';
    $cuentaInicial  = $empleado->CUENTAINICIAL ?? $empleado->cuentaInicial ?? 'PENDIENTE DE ASIGNAR';
    $cargo          = $empleado->PUESTO ?? $empleado->cargo ?? '-';
    $unidad         = $empleado->UR ?? $empleado->unidad ?? '-';
    $rfc            = $empleado->RFC ?? $empleado->rfc ?? '-';
    
    // Cálculo de importe neto considerando el descuento de pensiones (si aplica)
    
    // Cálculo del importe considerando pensiones
    $rawImporte = (float) (
        $empleado->IMPORTE ??
        $empleado->importe ??
        0
    );

    // Si el controlador ya calculó el importe neto,
    // conservar ese valor para empleados sin pensiones.
    if (isset($empleado->IMPORTE_NETO)) {
        $rawImporte = (float) $empleado->IMPORTE_NETO;
    }


    $importe = $rawImporte > 0 
        ? '$' . number_format($rawImporte, 2, '.', ',') 
        : '-';

    $telefono       = $empleado->TELEFONO ?? $empleado->telefono ?? '';
    $correo         = $empleado->CORREO ?? $empleado->correo ?? '';
    $fechaActual    = date('d/m/Y');
    $fechaHoraMeta  = date('Y-m-d H:i');

    $digits = str_split(str_pad(preg_replace('/[^0-9]/', '', $cuentaInicial), 11, '0', STR_PAD_LEFT));
@endphp

{{-- ========================================================================= --}}
{{-- BLOQUE 1: FORMATO DE REPOSICIÓN (Si existe tarjeta de reposición)          --}}
{{-- ========================================================================= --}}
@if(isset($tieneReposicion) && $tieneReposicion)
<div class="page-container">
    <table class="header-table">
        <tr>
            <td>
                <div class="institution-title">
                    COORDINACIÓN DE ADMINISTRACIÓN Y FINANZAS<br>
                    DIRECCIÓN GENERAL DE RECURSOS HUMANOS
                </div>
            </td>
        </tr>
    </table>

    <table class="title-block">
        <tr>
            <td class="title-main">FORMATO DE RECEPCIÓN</td>
        </tr>
        <tr>
            <td class="title-sub">
                EN LA MODALIDAD DE TARJETA ELECTRÓNICA<br>
                DE REPOSICIÓN DE LA MEDIDA DE FIN DE AÑO 2026
            </td>
        </tr>
    </table>

    <table class="doc-table">
        <tr>
            <td colspan="4" class="bg-section-header">DATOS DE LA ENTREGA</td>
        </tr>
        <tr>
            <td class="bg-label" style="width: 30%;">FECHA</td>
            <td class="bg-label" colspan="3" style="width: 70%;">NOMBRE DE QUIEN RECIBE</td>
        </tr>
        <tr>
            <td class="text-center bold" style="height: 30px;">{{ $fechaActual }}</td>
            <td class="text-center bold" colspan="3" style="font-size: 11.5px;">{{ $nombreCompleto }}</td>
        </tr>
        <tr>
            <td class="bg-label">EXPEDIENTE</td>
            <td class="bg-label">GRADO</td>
            <td class="bg-label">RFC</td>
            <td class="bg-label">IMPORTE</td>
        </tr>
        <tr>
            <td class="text-center bold font-mono" style="height: 30px;">{{ $numeroEmpleado }}</td>
            <td class="text-center bold">{{ $cargo }}</td>
            <td class="text-center bold font-mono">{{ $rfc }}</td>
            <td class="text-center bold font-mono">{{ $importe }}</td>
        </tr>
        <tr>
            <td colspan="4" class="bg-label">ADSCRIPCIÓN</td>
        </tr>
        <tr>
            <td colspan="4" class="text-center bold" style="height: 35px;">
                {{ $unidad }}<br>
                SECRETARÍA DE SEGURIDAD Y PROTECCIÓN CIUDADANA
            </td>
        </tr>
    </table>

    <table class="doc-table">
        <tr>
            <td class="bg-label" colspan="11">NÚMERO DE TARJETA</td>
        </tr>
        <tr>
            @foreach(array_slice($digits, 0, 11) as $digit)
                <td class="digit-cell">{{ $digit }}</td>
            @endforeach
        </tr>
    </table>

    <table class="doc-table">
        <tr>
            <td colspan="2" class="bg-section-header" style="width: 65%;">DATOS DE QUIEN RECIBE</td>
            <td class="bg-section-header" style="width: 35%;">VO. BO.</td>
        </tr>
        <tr>
            <td class="box-signature" style="width: 32.5%;">NOMBRE Y FIRMA</td>
            <td class="box-signature" style="width: 32.5%;">HUELLA DIGITAL</td>
            <td class="box-signature" style="width: 35%;">NOMBRE Y FIRMA</td>
        </tr>
        <tr>
            <td class="bg-label">TELÉFONO</td>
            <td class="text-center bold" style="height: 28px;">{{ $telefono }}</td>
            <td rowspan="2" class="legal-constancy">
                HAGO CONSTAR QUE LOS DATOS PLASMADOS EN ESTE FORMATO FUERON DEBIDAMENTE VERIFICADOS Y SE ENCUENTRAN CORRECTOS.
            </td>
        </tr>
        <tr>
            <td class="bg-label">CORREO ELECTRÓNICO</td>
            <td class="text-center bold">{{ $correo }}</td>
        </tr>
    </table>

    <div class="meta-info">
        USUARIO QUE GENERA: 70490 &nbsp;|&nbsp; FECHA Y HORA: {{ $fechaHoraMeta }}
    </div>
</div>

{{-- ========================================================================= --}}
{{-- BLOQUE 2: FORMATO INICIAL DE ENTREGA (Caso estándar)                      --}}
{{-- ========================================================================= --}}
@else
<div class="page-container">
    <table class="header-table">
        <tr>
            <td>
                <div class="institution-title">
                    COORDINACIÓN DE ADMINISTRACIÓN Y FINANZAS<br>
                    DIRECCIÓN GENERAL DE RECURSOS HUMANOS
                </div>
            </td>
        </tr>
    </table>

    <table class="title-block">
        <tr>
            <td class="title-main">FORMATO DE RECEPCIÓN</td>
        </tr>
        <tr>
            <td class="title-sub">
                EN LA MODALIDAD DE TARJETA ELECTRÓNICA<br>
                DE LA MEDIDA DE FIN DE AÑO 2026
            </td>
        </tr>
    </table>

    <table class="doc-table">
        <tr>
            <td colspan="4" class="bg-section-header">DATOS DE LA ENTREGA</td>
        </tr>
        <tr>
            <td class="bg-label" style="width: 30%;">FECHA</td>
            <td class="bg-label" colspan="3" style="width: 70%;">NOMBRE DE QUIEN RECIBE</td>
        </tr>
        <tr>
            <td class="text-center bold" style="height: 30px;">{{ $fechaActual }}</td>
            <td class="text-center bold" colspan="3" style="font-size: 11.5px;">{{ $nombreCompleto }}</td>
        </tr>
        <tr>
            <td class="bg-label">EXPEDIENTE</td>
            <td class="bg-label">GRADO / CARGO</td>
            <td class="bg-label">RFC</td>
            <td class="bg-label">IMPORTE</td>
        </tr>
        <tr>
            <td class="text-center bold font-mono" style="height: 30px;">{{ $numeroEmpleado }}</td>
            <td class="text-center bold">{{ $cargo }}</td>
            <td class="text-center bold font-mono">{{ $rfc }}</td>
            <td class="text-center bold font-mono">{{ $importe }}</td>
        </tr>
        <tr>
            <td colspan="4" class="bg-label">ADSCRIPCIÓN</td>
        </tr>
        <tr>
            <td colspan="4" class="text-center bold" style="height: 35px;">{{ $unidad }}</td>
        </tr>
    </table>

    <table class="doc-table">
        <tr>
            <td class="bg-label" colspan="11">NÚMERO DE TARJETA</td>
        </tr>
        <tr>
            @foreach(array_slice($digits, 0, 11) as $digit)
                <td class="digit-cell">{{ $digit }}</td>
            @endforeach
        </tr>
    </table>

    <table class="doc-table">
        <tr>
            <td colspan="2" class="bg-section-header" style="width: 65%;">DATOS DE QUIEN RECIBE</td>
            <td class="bg-section-header" style="width: 35%;">VO. BO.</td>
        </tr>
        <tr>
            <td class="box-signature" style="width: 32.5%;">NOMBRE Y FIRMA</td>
            <td class="box-signature" style="width: 32.5%;">HUELLA DIGITAL</td>
            <td class="box-signature" style="width: 35%;">NOMBRE Y FIRMA</td>
        </tr>
        <tr>
            <td class="bg-label">TELÉFONO</td>
            <td class="text-center bold" style="height: 28px;">{{ $telefono }}</td>
            <td rowspan="2" class="legal-constancy">
                HAGO CONSTAR QUE LOS DATOS PLASMADOS EN ESTE FORMATO FUERON DEBIDAMENTE VERIFICADOS Y SE ENCUENTRAN CORRECTOS.
            </td>
        </tr>
        <tr>
            <td class="bg-label">CORREO ELECTRÓNICO</td>
            <td class="text-center bold">{{ $correo }}</td>
        </tr>
    </table>

    <div class="meta-info">
        USUARIO QUE GENERA: 70490 &nbsp;|&nbsp; FECHA Y HORA: {{ $fechaHoraMeta }}
    </div>
</div>
@endif

</body>
</html>