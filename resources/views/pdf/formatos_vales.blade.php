<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: letter portrait;
            margin: 8mm 10mm 8mm 10mm;
        }
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.3;
        }
        .page-break {
            page-break-after: always;
        }
        
        /* Contenedor que fuerza el uso de toda la altura de la página */
        .sheet-container {
            width: 100%;
            height: 96vh;
            max-height: 985px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Encabezado Institucional */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .institution-title {
            font-size: 9px;
            font-weight: bold;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .date-box {
            text-align: right;
            font-size: 9px;
            font-weight: bold;
        }

        /* Títulos del Documento */
        .doc-title {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            margin: 10px 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-subtitle {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        /* Tablas de Estilo Excel extendidas */
        table.excel-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }
        table.excel-grid th, table.excel-grid td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 9.5px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        .bg-gray {
            background-color: #dcdcdc;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }

        /* Bloque de Firmas espaciado para cubrir la parte inferior */
        .signature-section {
            margin-top: 30px;
            margin-bottom: 15px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-table td {
            border: none;
            text-align: center;
            font-size: 9px;
            vertical-align: top;
            width: 50%;
            padding: 0 20px;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 100%;
            margin: 55px auto 6px auto;
        }

        /* Nota al pie y metadatos */
        .footer-note {
            font-size: 8.5px;
            text-align: justify;
            border: 1px solid #000;
            padding: 8px 10px;
            margin-top: 10px;
            background-color: #f9f9f9;
        }
        .meta-info {
            font-size: 8px;
            text-align: right;
            margin-top: 8px;
            color: #222;
        }
    </style>
</head>
<body>

    <!-- ========================================================================= -->
    <!-- HOJA 1: INTEGRANTE                                                        -->
    <!-- ========================================================================= -->
    <div class="sheet-container">
        <div>
            <table class="header-table">
                <tr>
                    <td style="width: 70%;">
                        <div class="institution-title">
                            GUARDIA NACIONAL<br>
                            COORDINACIÓN DE ADMINISTRACIÓN Y FINANZAS<br>
                            DIRECCIÓN GENERAL DE RECURSOS HUMANOS
                        </div>
                    </td>
                    <td style="width: 30%;">
                        <div class="date-box">FECHA: {{ date('d/m/Y') }}</div>
                    </td>
                </tr>
            </table>

            <div class="doc-title">FORMATO DE RECEPCIÓN DE INTEGRANTE</div>
            <div class="doc-subtitle">EN LA MODALIDAD DE TARJETA ELECTRÓNICA DE LA MEDIDA DE FIN DE AÑO 2026</div>

            <table class="excel-grid">
                <tr>
                    <td colspan="2" class="bg-gray text-center">DATOS DE LA ENTREGA</td>
                </tr>
                <tr>
                    <td class="bold" style="width: 25%;">FECHA</td>
                    <td class="bold" style="width: 75%;">NOMBRE DE QUIEN RECIBE</td>
                </tr>
                <tr>
                    <td>{{ date('d/m/Y') }}</td>
                    <td class="bold" style="font-size: 10.5px;">{{ strtoupper(($empleado->nomEmpleado ?? $empleado->nomempleado ?? '') . ' ' . ($empleado->appEmpleado ?? $empleado->appempleado ?? '')) }}</td>
                </tr>
            </table>

            <table class="excel-grid" style="margin-top: -8px;">
                <tr>
                    <td class="bold" style="width: 20%;">EXPEDIENTE</td>
                    <td class="bold" style="width: 35%;">GRADO / CARGO</td>
                    <td class="bold" style="width: 30%;">RFC</td>
                    <td class="bold" style="width: 15%;">IMPORTE</td>
                </tr>
                <tr>
                    <td class="text-center">{{ $empleado->numEmpleado ?? $empleado->numempleado ?? '-' }}</td>
                    <td>{{ $empleado->cargo ?? '-' }}</td>
                    <td class="bold text-center">{{ $empleado->rfc }}</td>
                    <td class="bold text-right">$14,900.00</td>
                </tr>
            </table>

            <table class="excel-grid" style="margin-top: -8px;">
                <tr>
                    <td class="bold" style="width: 20%;">ADSCRIPCIÓN</td>
                    <td style="width: 80%; font-size: 10px;">{{ $empleado->unidad ?? '-' }}</td>
                </tr>
            </table>

            <table class="excel-grid" style="margin-top: 10px;">
                <tr>
                    <td class="bold bg-gray" style="width: 40%;">IDENTIFICADOR</td>
                    <td class="bold bg-gray" style="width: 60%;">NÚMERO DE TARJETA</td>
                </tr>
                <tr>
                    <td style="padding: 10px;">TARJETA ELECTRÓNICA DE FIN DE AÑO</td>
                    <td class="bold text-center" style="font-size: 13px; background-color: #f5f5f5; padding: 10px;">
                        {{ $empleado->cuentaInicial ?? $empleado->cuentainicial ?? 'PENDIENTE DE ASIGNAR' }}
                    </td>
                </tr>
            </table>
        </div>

        <div>
            <div class="signature-section">
                <table class="signature-table">
                    <tr>
                        <td>
                            <div class="signature-line"></div>
                            <strong>NOMBRE Y FIRMA DE QUIEN RECIBE</strong>
                        </td>
                        <td>
                            <div class="signature-line"></div>
                            <strong>VO. BO. / NOMBRE Y FIRMA</strong>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer-note">
                HAGO CONSTAR QUE LOS DATOS PLASMADOS EN ESTE FORMATO FUERON DEBIDAMENTE VERIFICADOS Y SE ENCUENTRAN CORRECTOS.
            </div>

            <div class="meta-info">
                USUARIO QUE GENERA: 70490 &nbsp;|&nbsp; FECHA Y HORA: {{ date('Y-m-d H:i') }}
            </div>
        </div>
    </div>

    <div class="page-break"></div>

    <!-- ========================================================================= -->
    <!-- HOJA 2: INTEGRANTE CON PENSIÓN ALIMENTICIA                                -->
    <!-- ========================================================================= -->
    <div class="sheet-container">
        <div>
            <table class="header-table">
                <tr>
                    <td style="width: 70%;">
                        <div class="institution-title">
                            GUARDIA NACIONAL<br>
                            COORDINACIÓN DE ADMINISTRACIÓN Y FINANZAS<br>
                            DIRECCIÓN GENERAL DE RECURSOS HUMANOS
                        </div>
                    </td>
                    <td style="width: 30%;">
                        <div class="date-box">FECHA: {{ date('d/m/Y') }}</div>
                    </td>
                </tr>
            </table>

            <div class="doc-title">FORMATO DE RECEPCIÓN DE INTEGRANTE CON PENSIÓN ALIMENTICIA</div>
            <div class="doc-subtitle">EN LA MODALIDAD DE TARJETA ELECTRÓNICA DE LA MEDIDA DE FIN DE AÑO 2026</div>

            <table class="excel-grid">
                <tr>
                    <td colspan="2" class="bg-gray text-center">DATOS DE LA ENTREGA</td>
                </tr>
                <tr>
                    <td class="bold" style="width: 25%;">FECHA</td>
                    <td class="bold" style="width: 75%;">NOMBRE DE QUIEN RECIBE</td>
                </tr>
                <tr>
                    <td>{{ date('d/m/Y') }}</td>
                    <td class="bold" style="font-size: 10.5px;">{{ strtoupper(($empleado->nomEmpleado ?? $empleado->nomempleado ?? '') . ' ' . ($empleado->appEmpleado ?? $empleado->appempleado ?? '')) }}</td>
                </tr>
            </table>

            <table class="excel-grid" style="margin-top: -8px;">
                <tr>
                    <td class="bold" style="width: 20%;">EXPEDIENTE</td>
                    <td class="bold" style="width: 35%;">GRADO / CARGO</td>
                    <td class="bold" style="width: 30%;">RFC</td>
                    <td class="bold" style="width: 15%;">IMPORTE</td>
                </tr>
                <tr>
                    <td class="text-center">{{ $empleado->numEmpleado ?? $empleado->numempleado ?? '-' }}</td>
                    <td>{{ $empleado->cargo ?? '-' }}</td>
                    <td class="bold text-center">{{ $empleado->rfc }}</td>
                    <td class="bold text-right">$14,900.00</td>
                </tr>
            </table>

            <table class="excel-grid" style="margin-top: -8px;">
                <tr>
                    <td class="bold" style="width: 20%;">ADSCRIPCIÓN</td>
                    <td style="width: 80%; font-size: 10px;">{{ $empleado->unidad ?? '-' }}</td>
                </tr>
            </table>

            <table class="excel-grid" style="margin-top: 10px;">
                <tr>
                    <td class="bold bg-gray" style="width: 40%;">IDENTIFICADOR</td>
                    <td class="bold bg-gray" style="width: 60%;">NÚMERO DE TARJETA (PENSIÓN)</td>
                </tr>
                <tr>
                    <td style="padding: 10px;">TARJETA ELECTRÓNICA (PENSIÓN ALIMENTICIA)</td>
                    <td class="bold text-center" style="font-size: 13px; background-color: #f5f5f5; padding: 10px;">
                        {{ $empleado->cuentaInicial ?? $empleado->cuentainicial ?? 'PENDIENTE DE ASIGNAR' }}
                    </td>
                </tr>
            </table>

            <div style="border: 1px solid #000; padding: 8px 10px; margin-top: 10px; font-size: 9px; background-color: #fafafa; text-align: justify;">
                <strong>Integrante:</strong> Se le exhorta a informar a su(s) beneficiaria(os) de pensión alimenticia, que debe(n) de acudir a la sede de Xochimilco en la Ciudad de México, a recoger los vales de despensa que les corresponde, a partir del 7 Ene. 2026 al 12 Jun. 2026.
            </div>
        </div>

        <div>
            <div class="signature-section">
                <table class="signature-table">
                    <tr>
                        <td>
                            <div class="signature-line"></div>
                            <strong>NOMBRE Y FIRMA</strong>
                        </td>
                        <td>
                            <div class="signature-line"></div>
                            <strong>VO. BO.</strong>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="meta-info">
                USUARIO QUE GENERA: 70490 &nbsp;|&nbsp; FECHA Y HORA: {{ date('Y-m-d H:i') }}
            </div>
        </div>
    </div>

    <div class="page-break"></div>

    <!-- ========================================================================= -->
    <!-- HOJA 3: REPOSICIÓN                                                        -->
    <!-- ========================================================================= -->
    <div class="sheet-container">
        <div>
            <table class="header-table">
                <tr>
                    <td style="width: 70%;">
                        <div class="institution-title">
                            GUARDIA NACIONAL<br>
                            COORDINACIÓN DE ADMINISTRACIÓN Y FINANZAS<br>
                            DIRECCIÓN GENERAL DE RECURSOS HUMANOS
                        </div>
                    </td>
                    <td style="width: 30%;">
                        <div class="date-box">FECHA: {{ date('d/m/Y') }}</div>
                    </td>
                </tr>
            </table>

            <div class="doc-title">FORMATO DE RECEPCIÓN</div>
            <div class="doc-subtitle">EN LA MODALIDAD DE TARJETA ELECTRÓNICA DE REPOSICIÓN DE LA MEDIDA DE FIN DE AÑO 2026</div>

            <table class="excel-grid">
                <tr>
                    <td colspan="2" class="bg-gray text-center">DATOS DE LA ENTREGA</td>
                </tr>
                <tr>
                    <td class="bold" style="width: 25%;">FECHA</td>
                    <td class="bold" style="width: 75%;">NOMBRE DE QUIEN RECIBE</td>
                </tr>
                <tr>
                    <td>{{ date('d/m/Y') }}</td>
                    <td class="bold" style="font-size: 10.5px;">{{ strtoupper(($empleado->nomEmpleado ?? $empleado->nomempleado ?? '') . ' ' . ($empleado->appEmpleado ?? $empleado->appempleado ?? '')) }}</td>
                </tr>
            </table>

            <table class="excel-grid" style="margin-top: -8px;">
                <tr>
                    <td class="bold" style="width: 20%;">EXPEDIENTE</td>
                    <td class="bold" style="width: 35%;">GRADO</td>
                    <td class="bold" style="width: 30%;">RFC</td>
                    <td class="bold" style="width: 15%;">IMPORTE</td>
                </tr>
                <tr>
                    <td class="text-center">{{ $empleado->numEmpleado ?? $empleado->numempleado ?? '-' }}</td>
                    <td>{{ $empleado->cargo ?? '-' }}</td>
                    <td class="bold text-center">{{ $empleado->rfc }}</td>
                    <td class="bold text-right">$14,900.00</td>
                </tr>
            </table>

            <table class="excel-grid" style="margin-top: -8px;">
                <tr>
                    <td class="bold" style="width: 20%;">ADSCRIPCIÓN</td>
                    <td style="width: 80%; font-size: 10px;">
                        {{ $empleado->unidad ?? '-' }}<br>
                        SECRETARIA DE SEGURIDAD Y PROTECCIÓN CIUDADANA
                    </td>
                </tr>
            </table>

            <table class="excel-grid" style="margin-top: 10px;">
                <tr>
                    <td class="bold bg-gray" style="width: 40%;">IDENTIFICADOR</td>
                    <td class="bold bg-gray" style="width: 60%;">NÚMERO DE TARJETA (REPOSICIÓN)</td>
                </tr>
                <tr>
                    <td style="padding: 10px;">TARJETA ELECTRÓNICA DE REPOSICIÓN</td>
                    <td class="bold text-center" style="font-size: 13px; background-color: #f5f5f5; padding: 10px;">
                        {{ $empleado->cuentaInicial ?? $empleado->cuentainicial ?? 'PENDIENTE DE ASIGNAR' }}
                    </td>
                </tr>
            </table>
        </div>

        <div>
            <div class="signature-section">
                <table class="signature-table">
                    <tr>
                        <td>
                            <div class="signature-line"></div>
                            <strong>NOMBRE Y FIRMA DE QUIEN RECIBE</strong>
                        </td>
                        <td>
                            <div class="signature-line"></div>
                            <strong>VO. BO.</strong>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer-note">
                HAGO CONSTAR QUE LOS DATOS PLASMADOS EN ESTE FORMATO FUERON DEBIDAMENTE VERIFICADOS Y SE ENCUENTRAN CORRECTOS.
            </div>

            <div class="meta-info">
                USUARIO QUE GENERA: 70490 &nbsp;|&nbsp; FECHA Y HORA: {{ date('Y-m-d H:i') }}
            </div>
        </div>
    </div>

</body>
</html>