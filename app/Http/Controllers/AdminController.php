<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Card;
use App\Models\Acta;
use App\Models\Pension;
use App\Models\Register;
use App\Models\Document;
use App\Models\TypeActa;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Yajra\DataTables\Facades\DataTables;
use PhpOffice\PhpWord\TemplateProcessor;
use Luecano\NumeroALetras\NumeroALetras;

class AdminController extends Controller
{
    public function obtenerRegistrosVales(Request $request)
    {
        try {
            $estatus = $request->query('estatus', 'todos');

            $query = Register::with('documentos')
                ->select([
                    'IDREGISTER',
                    'NOMBRECOMPLETO',
                    'NUMEMPLEADO',
                    'NOMEMPLEADO',
                    'APPEMPLEADO',
                    'APMEMPLEADO',
                    'UR',
                    'NSS',
                    'NIVEL',
                    'PUESTO',
                    'UBICACION',
                    'IDENLACE',
                    'CLAVE',
                    'CUENTAINICIAL',
                    'MODALIDAD',
                    'IDSTATUS',
                    'ORIGEN',
                    'PENSION',
                    'IMPORTE',
                    'FECHAENTREGA',
                    'CREATED_AT',
                    'UPDATED_AT',
                ])
                ->whereYear('CREATED_AT', now()->year);

            if ($estatus !== 'todos' && $estatus !== null) {
                $query->where('IDSTATUS', $estatus);
            }
            $this->filtrarRegistrosPorEnlace($query);

            $registros = $query->orderBy('IDREGISTER', 'desc')->get();

            return response()->json([
                'status' => true,
                'data' => $registros,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en obtenerRegistrosVales: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al consultar registros: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function actualizarEstatusVale(Request $request)
    {
        $request->validate([
            'idRegister' => 'required',
            'idStatus' => 'required|integer|in:1,2,3,4',
        ]);

        try {
            $idRegister = $request->input('idRegister');
            $nuevoEstatus = (int) $request->input('idStatus');

            // Preparamos los datos a actualizar de forma limpia y sin duplicados
            $datosActualizar = [
                'IDSTATUS' => $nuevoEstatus,
                'UPDATED_AT' => now(),
            ];

            // Si se selecciona "Entregada" (2), guardamos la fecha y hora de entrega
            if ($nuevoEstatus === 2) {
                // Nota: Si tu columna en Oracle se llama FECHA_ENTREGA en lugar de FECHAENTREGA, 
                // solo cambia la clave del array aquí abajo:
                $datosActualizar['FECHAENTREGA'] = now();
            }

            $afectados = DB::connection('oracle_primary')
                ->table('REGISTERS')
                ->where('IDREGISTER', $idRegister) // <- Aquí estaba el fallo del is null
                ->update($datosActualizar);

            if ($afectados === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el registro especificado.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Estatus actualizado correctamente.',
                'fechaEntrega' => $nuevoEstatus === 2 ? now('America/Mexico_City')->toIso8601String() : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estatus: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }
    public function uploadTarjetas(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file = $request->file('file');

            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (count($rows) <= 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'El archivo Excel está vacío o no contiene registros.'
                ], 400);
            }

            $header = array_map('trim', array_map('strtoupper', $rows[1]));
            $cuentaIndex = array_search('CUENTA', $header);
            $folioIndex = array_search('FOLIO', $header);

            if ($cuentaIndex === false || $folioIndex === false) {
                return response()->json([
                    'status' => false,
                    'message' => 'El archivo Excel debe contener las columnas exactas: CUENTA y FOLIO.'
                ], 400);
            }

            $sheetData = $sheet->toArray(null, true, true, true);
            $cabeceras = array_values($sheetData[1]);

            $tarjetasInsertadas = [];

            for ($i = 2; $i <= count($sheetData); $i++) {
                $filaRaw = $sheetData[$i];
                $filaAsociativa = [];
                $colIndex = 0;
                foreach ($filaRaw as $val) {
                    if (isset($cabeceras[$colIndex])) {
                        $nombreColumna = trim(strtoupper($cabeceras[$colIndex]));
                        $filaAsociativa[$nombreColumna] = trim($val);
                    }
                    $colIndex++;
                }

                $cuenta = $filaAsociativa['CUENTA'] ?? null;
                $folio = $filaAsociativa['FOLIO'] ?? null;

                if (!empty($cuenta) && !empty($folio)) {
                    \App\Models\Card::updateOrCreate(
                        ['cuenta' => $cuenta],
                        ['folio' => $folio]
                    );

                    $tarjetasInsertadas[] = [
                        'cuenta' => $cuenta,
                        'folio' => $folio
                    ];
                }
            }

            if (empty($tarjetasInsertadas)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontraron registros válidos para procesar.'
                ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => 'Tarjetas cargadas y registradas correctamente.',
                'tarjetas' => $tarjetasInsertadas
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cargarTarjetas(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
            'importe' => ['required', 'numeric', 'min:0.01'],
        ]);

        $importe = $request->input('importe');
        $file = $request->file('archivo');
        $extension = strtolower($file->getClientOriginalExtension());
        $filas = [];

        try {
            if (in_array($extension, ['csv', 'txt'], true)) {
                $path = $file->getRealPath();
                $contenido = file_get_contents($path);
                $delimitador = substr_count($contenido, ';') > substr_count($contenido, ',')
                    ? ';'
                    : ',';

                $handle = fopen($path, 'r');

                if ($handle === false) {
                    throw new \RuntimeException('No se pudo leer el archivo.');
                }

                $numeroFila = 0;

                while (($data = fgetcsv($handle, 0, $delimitador)) !== false) {
                    if ($numeroFila++ === 0) {
                        continue;
                    }

                    $filas[] = [
                        'cuenta' => isset($data[1]) ? trim((string) $data[1]) : null,
                        'rfc' => isset($data[3]) ? strtoupper(trim((string) $data[3])) : null,
                        'curp' => isset($data[4]) ? strtoupper(trim((string) $data[4])) : null,
                    ];
                }

                fclose($handle);
            } else {
                $spreadsheet = IOFactory::load($file->getRealPath());
                $hoja = $spreadsheet->getActiveSheet();

                foreach ($hoja->toArray(null, true, true, false) as $indice => $row) {
                    if ($indice === 0) {
                        continue;
                    }

                    $filas[] = [
                        'cuenta' => isset($row[1]) ? trim((string) $row[1]) : null,
                        'rfc' => isset($row[3]) ? strtoupper(trim((string) $row[3])) : null,
                        'curp' => isset($row[4]) ? strtoupper(trim((string) $row[4])) : null,
                    ];
                }
            }

            $actualizados = 0;
            $noEncontrados = 0;

            DB::connection('oracle_primary')->transaction(function () use (
                $filas,
                $importe,
                &$actualizados,
                &$noEncontrados
            ) {
                foreach ($filas as $fila) {
                    $cuenta = $fila['cuenta'];
                    $rfc = $fila['rfc'];
                    $curp = $fila['curp'];

                    if (!$rfc && !$curp) {
                        continue;
                    }

                    $consulta = DB::connection('oracle_primary')
                        ->table('registers')
                        ->where(function ($query) use ($rfc, $curp) {
                            if ($rfc) {
                                $query->orWhereRaw('UPPER(RFC) = ?', [$rfc]);
                            }

                            if ($curp) {
                                $query->orWhereRaw('UPPER(CURP) = ?', [$curp]);
                            }
                        });

                    $datosAActualizar = [
                        'IMPORTE' => $importe,
                    ];

                    if (!empty($cuenta)) {
                        $datosAActualizar['CUENTAINICIAL'] = $cuenta;
                    }

                    $afectados = $consulta->update($datosAActualizar);

                    if ($afectados > 0) {
                        $actualizados++;
                    } else {
                        $noEncontrados++;
                    }
                }
            });

            return response()->json([
                'status' => true,
                'message' => "Carga exitosa. Registros actualizados: {$actualizados}. No encontrados en registros: {$noEncontrados}.",
                'actualizados' => $actualizados,
                'noEncontrados' => $noEncontrados,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar tarjetas e importe en Oracle: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function cargarPensiones(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $file = $request->file('archivo');
        $extension = strtolower($file->getClientOriginalExtension());
        $filas = [];

        try {
            if (in_array($extension, ['csv', 'txt'], true)) {
                $path = $file->getRealPath();
                $contenido = file_get_contents($path);
                $delimitador = substr_count($contenido, ';') > substr_count($contenido, ',')
                    ? ';'
                    : ',';

                $handle = fopen($path, 'r');

                if ($handle === false) {
                    throw new \RuntimeException('No se pudo leer el archivo de pensiones.');
                }

                $numeroFila = 0;

                while (($data = fgetcsv($handle, 0, $delimitador)) !== false) {
                    if ($numeroFila++ === 0) {
                        continue;
                    }

                    $filas[] = [
                        'numEmpleado' => isset($data[0]) ? trim((string) $data[0]) : null,
                        'rfcPensionada' => isset($data[3]) ? strtoupper(trim((string) $data[3])) : null,
                        'curpPensionada' => isset($data[4]) ? strtoupper(trim((string) $data[4])) : null,
                        'cuenta' => isset($data[6]) ? trim((string) $data[6]) : null,
                    ];
                }

                fclose($handle);
            } else {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
                $hoja = $spreadsheet->getActiveSheet();

                foreach ($hoja->toArray(null, true, true, false) as $indice => $row) {
                    if ($indice === 0) {
                        continue;
                    }

                    $filas[] = [
                        'numEmpleado' => isset($row[0]) ? trim((string) $row[0]) : null,
                        'rfcPensionada' => isset($row[3]) ? strtoupper(trim((string) $row[3])) : null,
                        'curpPensionada' => isset($row[4]) ? strtoupper(trim((string) $row[4])) : null,
                        'cuenta' => isset($row[6]) ? trim((string) $row[6]) : null,
                    ];
                }
            }

            $actualizados = 0;
            $noEncontrados = 0;

            DB::connection('oracle_primary')->transaction(function () use (
                $filas,
                &$actualizados,
                &$noEncontrados
            ) {
                foreach ($filas as $fila) {
                    $numEmpleado = $fila['numEmpleado'];
                    $rfcPensionada = $fila['rfcPensionada'];
                    $curpPensionada = $fila['curpPensionada'];
                    $cuenta = $fila['cuenta'];

                    if (!$cuenta || (!$numEmpleado && !$rfcPensionada && !$curpPensionada)) {
                        continue;
                    }

                    $consulta = DB::connection('oracle_primary')
                        ->table('pensiones')
                        ->where(function ($query) use ($numEmpleado, $rfcPensionada, $curpPensionada) {
                            if ($numEmpleado) {
                                $query->orWhere('NUMEMPLEADO', $numEmpleado);
                            }
                            if ($rfcPensionada) {
                                $query->orWhereRaw('UPPER(RFC_PENSIONADA) = ?', [$rfcPensionada]);
                            }
                            if ($curpPensionada) {
                                $query->orWhereRaw('UPPER(CURP_PENSIONADA) = ?', [$curpPensionada]);
                            }
                        });

                    $afectados = $consulta->update([
                        'cuenta' => $cuenta,
                    ]);

                    if ($afectados > 0) {
                        $actualizados++;
                    } else {
                        $noEncontrados++;
                    }
                }
            });

            return response()->json([
                'status' => true,
                'message' => "Carga de pensiones exitosa. Cuentas actualizadas: {$actualizados}. No encontrados: {$noEncontrados}.",
                'actualizados' => $actualizados,
                'noEncontrados' => $noEncontrados,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar pensiones en Oracle: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al procesar el archivo de pensiones: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function descargarFormatos($idRegister)
    {
        if (!$idRegister || $idRegister === 'undefined') {
            return response()->json([
                'status' => false,
                'message' => 'El ID del registro es inválido.',
            ], 400);
        }

        try {
            $consultaEmpleado = Register::query()->where('IDREGISTER', $idRegister);
            $this->filtrarRegistrosPorEnlace($consultaEmpleado);
            $empleado = $consultaEmpleado->first();

            if (!$empleado) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el registro en la base de datos.',
                ], 404);
            }

            $numeroEmpleado = trim((string) ($empleado->numempleado ?? $empleado->NUMEMPLEADO ?? $idRegister));

            $pensionados = Pension::where('NUMEMPLEADO', '=', $numeroEmpleado)->get();

            if ($pensionados->isEmpty() && method_exists($empleado, 'pensionados')) {
                $pensionados = $empleado->pensionados()->get();
            }

            $rawImporte = (float) ($empleado->IMPORTE ?? $empleado->importe ?? 0);

            if ($pensionados->isNotEmpty()) {
                $porcentajeTotal = $pensionados->sum(function ($p) {
                    return (float) ($p->PORCENTAJE ?? $p->porcentaje ?? 0);
                });

                $descuento = ($rawImporte * $porcentajeTotal) / 100;
                $rawImporte = max(0, $rawImporte - $descuento);
            }

            $empleado->IMPORTE_NETO = $rawImporte;

            $templatePath = storage_path('app/templates/formato_entrega.docx');

            if (!file_exists($templatePath)) {
                throw new \Exception('La plantilla de Word no existe en el servidor.');
            }

            $templateProcessor = new TemplateProcessor($templatePath);

            $importeNeto = $empleado->IMPORTE_NETO;

            $formatter = new NumeroALetras();

            $importeEnLetra = $formatter->toMoney($importeNeto, 2, 'PESOS', 'CENTAVOS');

            $templateProcessor->setValue('cuenta', $empleado->CUENTAINICIAL ?? $empleado->cuentaInicial ?? $empleado->cuentainicial ?? 'N/A');
            $templateProcessor->setValue('importe', number_format($importeNeto, 2, '.', ','));
            $templateProcessor->setValue('importe_letra', mb_strtoupper($importeEnLetra));

            $templateProcessor->setValue('nombreCompleto', $empleado->nombrecompleto ?? $empleado->nombre ?? 'N/A');
            $templateProcessor->setValue('numEmpleado', $numeroEmpleado);
            $templateProcessor->setValue('nombreBeneficiario', $empleado->NOMBRE_BENEFICIARIO ?? '________________________________________');
            $templateProcessor->setValue('enlace', $empleado->idenlace ?? 'NOMBRE DEL ENLACE');

            $templateProcessor->setValue('dia', date('d'));
            $templateProcessor->setValue('mes', date('m'));
            $templateProcessor->setValue('anio', date('Y'));

            $rfc = strtoupper(trim($empleado->rfc ?? ''));
            $charsRfc = str_pad($rfc, 13, ' ', STR_PAD_RIGHT);

            for ($i = 0; $i < 13; $i++) {
                $templateProcessor->setValue("r{$i}", $charsRfc[$i]);
            }

            $fileName = "formato_entrega_{$numeroEmpleado}.docx";
            $tempFile = storage_path("app/public/{$fileName}");

            $templateProcessor->saveAs($tempFile);

            return response()->download($tempFile)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Error al generar el formato Word de entrega: ' . $e->getMessage(), [
                'idRegister' => $idRegister
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al generar el documento Word.',
            ], 500);
        }
    }


    public function descargarFormatoPensionadoIndividual($idRegister, $idPension)
    {
        if (!$idRegister || $idRegister === 'undefined' || !$idPension) {
            return response()->json([
                'status' => false,
                'message' => 'Los parámetros proporcionados son inválidos.',
            ], 400);
        }

        try {
            $consultaEmpleado = Register::query()->where('IDREGISTER', $idRegister);
            $this->filtrarRegistrosPorEnlace($consultaEmpleado);
            $empleado = $consultaEmpleado->first();

            if (!$empleado) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el empleado.',
                ], 404);
            }

            $numeroEmpleado = trim((string) ($empleado->numempleado ?? $empleado->NUMEMPLEADO ?? ''));

            $pensionadoEspecifico = Pension::where('NUMEMPLEADO', '=', $numeroEmpleado)
                ->where('IDPENSION', $idPension)
                ->first();

            if (!$pensionadoEspecifico) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el registro del pensionado.',
                ], 404);
            }

            $importeTotalEmpleado = (float) ($empleado->IMPORTE ?? $empleado->importe ?? 0);
            $porcentajePension = (float) ($pensionadoEspecifico->PORCENTAJE ?? $pensionadoEspecifico->porcentaje ?? 0);

            $descuentoIndividual = ($importeTotalEmpleado * $porcentajePension) / 100;
            $importeNetoPensionado = max(0, $importeTotalEmpleado - $descuentoIndividual);

            $templatePath = storage_path('app/templates/formato_pensionados.docx');

            if (!file_exists($templatePath)) {
                throw new \Exception('La plantilla de Word para pensionados no existe en el servidor.');
            }

            $templateProcessor = new TemplateProcessor($templatePath);

            $formatter = new NumeroALetras();
            $importeEnLetra = $formatter->toMoney($descuentoIndividual, 2, 'PESOS', 'CENTAVOS');

            $templateProcessor->setValue('cuenta', $empleado->CUENTAINICIAL ?? $empleado->cuentaInicial ?? 'N/A');
            $templateProcessor->setValue('importe', number_format($descuentoIndividual, 2, '.', ','));
            $templateProcessor->setValue('importe_letra', mb_strtoupper($importeEnLetra));

            $templateProcessor->setValue('nombreCompleto', $empleado->nombrecompleto ?? $empleado->nombre ?? 'N/A');
            $templateProcessor->setValue('numEmpleado', $numeroEmpleado);
            $templateProcessor->setValue('correo', $empleado->CORREO ?? 'N/A');
            $templateProcessor->setValue('telefono', $empleado->TELEFONO ?? 'N/A');
            $templateProcessor->setValue('enlace', $empleado->idenlace ?? 'NOMBRE DEL ENLACE');

            $templateProcessor->setValue('pensionado_porcentaje', $porcentajePension);
            $templateProcessor->setValue('pensionado_nombre', $pensionadoEspecifico->NOMBREPENSIONADO ?? $pensionadoEspecifico->nombrepensionado ?? 'N/A');

            $templateProcessor->setValue('dia', date('d'));
            $templateProcessor->setValue('mes', date('m'));
            $templateProcessor->setValue('anio', date('Y'));

            $rfc = strtoupper(trim($empleado->RFC ?? ''));
            $charsRfc = str_pad($rfc, 13, ' ', STR_PAD_RIGHT);

            for ($i = 0; $i < 13; $i++) {
                $templateProcessor->setValue("r{$i}", $charsRfc[$i]);
            }

            $fileName = "pensionado_{$idPension}_{$numeroEmpleado}.docx";
            $tempFile = storage_path("app/public/{$fileName}");

            $templateProcessor->saveAs($tempFile);

            return response()->download($tempFile)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Error al generar formato Word del pensionado individual', [
                'idRegister' => $idRegister,
                'idPension' => $idPension,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No se pudo generar el documento Word del pensionado.',
            ], 500);
        }
    }

    public function users()
    {
        $typeUsers = DB::connection('oracle_primary')
            ->table('typeUser')
            ->get();

        return view('admin.users', compact('typeUsers'));
    }

    public function datosEmpleado(Request $request)
    {
        $request->validate([
            'empleado' => ['required', 'string'],
            'origen' => ['required', 'in:secretaría,guardia'],
        ]);

        $numeroEmpleado = explode('-', $request->input('empleado'))[0];
        $usuarioActual = Auth::user();

        if ($request->input('origen') === 'guardia') {
            $conexion = 'gnnomina';
            $tabla = 'EMPLEADOS_PRESTACIONES_GN';
            $fuente = 'Guardia Nacional';
        } else {
            $conexion = 'oracle_secondary';
            $tabla = 'EMPLEADOS_PRESTACIONES';
            $fuente = 'la Secretaría';
        }

        try {
            $empleado = $this->buscarEnConexion(
                $conexion,
                $tabla,
                $numeroEmpleado,
                $usuarioActual
            );

            if (!$empleado) {
                return response()->json([
                    'error' => "Empleado no encontrado en {$fuente} o fuera de su unidad.",
                ], 404);
            }

            return response()->json([
                'num_empleado' => $empleado->num_empleado,
                'nombre' => $empleado->nombre,
                'apellido_paterno' => $empleado->apellido_paterno,
                'apellido_materno' => $empleado->apellido_materno,
                'nivel' => $empleado->nivel,
                'unidad' => $empleado->unidad,
            ]);
        } catch (\Throwable $e) {
            Log::error("Error consultando empleados en {$conexion}: " . $e->getMessage());

            return response()->json([
                'error' => "No se pudo consultar la información de {$fuente}.",
            ], 503);
        }
    }

    private function buscarEnConexion(
        string $conexion,
        string $tabla,
        string $numeroEmpleado,
        $usuarioActual
    ) {
        $consulta = DB::connection($conexion)
            ->table($tabla)
            ->where('NUM_EMPLEADO', $numeroEmpleado);

        $tipoUsuario = $usuarioActual->idType
            ?? $usuarioActual->idtype
            ?? $usuarioActual->IDTYPE
            ?? null;

        if (in_array((int) $tipoUsuario, [2, 3], true)) {
            $unidad = $usuarioActual->unidad ?? $usuarioActual->UNIDAD ?? null;

            if ($unidad) {
                $consulta->where('UNIDAD', $unidad);
            } else {
                return null;
            }
        }

        return $consulta->first();
    }

    public function crearUsuario(Request $request)
    {
        $request->validate([
            'numEmpleado' => ['required', 'string', 'max:20'],
            'origen' => ['required', 'in:secretaría,guardia'],
            'nomUsuario' => ['required', 'string', 'max:255'],
            'appUsuario' => ['required', 'string', 'max:255'],
            'apmUsuario' => ['nullable', 'string', 'max:255'],
            'cargo' => ['required', 'string', 'max:255'],
            'unidad' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'idtype' => [Auth::user()->idType == 1 ? 'required' : 'nullable', 'integer'],
        ]);

        $origen = $request->input('origen');
        $numero = strtoupper(trim($request->input('numEmpleado')));
        $email = strtolower(trim($request->input('email')));

        $emailExiste = DB::connection('oracle_primary')
            ->table('users')
            ->whereRaw('UPPER(EMAIL) = ?', [strtoupper($email)])
            ->exists();

        if ($emailExiste) {
            return response()->json([
                'status' => false,
                'message' => 'Ese correo ya está registrado.',
            ], 409);
        }

        $numeroExiste = DB::connection('oracle_primary')
            ->table('users')
            ->whereRaw('UPPER(NUMEMPLEADO) = ?', [$numero])
            ->whereRaw('UPPER(ORIGEN) = ?', [strtoupper($origen)])
            ->exists();

        if ($numeroExiste) {
            return response()->json([
                'status' => false,
                'message' => 'Ese número de empleado ya está registrado para ese origen.',
            ], 409);
        }

        $tipoUsuarioActual = Auth::user()->idType
            ?? Auth::user()->idtype
            ?? Auth::user()->IDTYPE
            ?? null;

        $idType = (int) $tipoUsuarioActual === 1
            ? $request->integer('idtype')
            : 3;

        User::create([
            'numEmpleado' => $request->input('numEmpleado'),
            'firstName' => trim($request->input('nomUsuario')),
            'lastName' => trim($request->input('appUsuario')),
            'middleName' => trim($request->input('apmUsuario', '')),
            'nivel' => trim($request->input('cargo')),
            'unidad' => trim($request->input('unidad')),
            'email' => $email,
            'password' => Hash::make($request->input('password')),
            'origen' => $origen,
            'idType' => $idType,
            'active' => 0,
            'must_change_password' => 1,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'El usuario se creó correctamente.',
        ], 201);
    }

    public function editUser($idUser)
    {
        $usuario = User::findOrFail($idUser);

        $typeUsers = DB::connection('oracle_primary')
            ->table('typeUser')
            ->get();

        return view('admin.editUsuario', compact('usuario', 'typeUsers'));
    }

    public function actualizarUsuario(Request $request, $id_user)
    {
        $request->validate([
            'numEmpleado'   => ['required', 'string', 'max:20'],
            'origen'        => ['required', 'in:secretaría,guardia'],
            'nomUsuario'    => ['required', 'string', 'max:255'],
            'appUsuario'    => ['required', 'string', 'max:255'],
            'apmUsuario'    => ['nullable', 'string', 'max:255'],
            'cargo'         => ['required', 'string', 'max:255'],
            'unidad'        => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255'],
            'active'        => ['required', 'boolean'],
            'idtype'        => ['nullable', 'integer'],
            'nuevaPassword' => ['nullable', 'string', 'min:8'],
        ]);

        $idUsuario = (int) $id_user;
        $db = DB::connection('oracle_primary');

        $usuarioExiste = $db->table('users')
            ->where('ID_USER', $idUsuario)
            ->exists();

        if (!$usuarioExiste) {
            return response()->json([
                'message' => 'No se encontró el usuario.',
            ], 404);
        }

        $email = strtolower(trim($request->input('email')));
        $origen = $request->input('origen');
        $numero = strtoupper(trim($request->input('numEmpleado')));

        $errores = [];

        $emailExiste = $db->table('users')
            ->whereRaw('UPPER(EMAIL) = ?', [strtoupper($email)])
            ->where('ID_USER', '<>', $idUsuario)
            ->exists();

        if ($emailExiste) {
            $errores['email'] = [
                'Ese correo ya está registrado por otro usuario.',
            ];
        }

        $numeroExiste = $db->table('users')
            ->whereRaw('UPPER(NUMEMPLEADO) = ?', [$numero])
            ->whereRaw('UPPER(ORIGEN) = ?', [strtoupper($origen)])
            ->where('ID_USER', '<>', $idUsuario)
            ->exists();

        if ($numeroExiste) {
            $errores['numEmpleado'] = [
                'Ese número de empleado ya está registrado para ese origen.',
            ];
        }

        if ($errores) {
            return response()->json([
                'message' => 'No se pudo actualizar el usuario.',
                'errors' => $errores,
            ], 422);
        }

        $datos = [
            'NUMEMPLEADO' => $numero,
            'firstname'   => trim($request->input('nomUsuario')),
            'lastname'    => trim($request->input('appUsuario')),
            'middlename'  => trim($request->input('apmUsuario', '')),
            'NIVEL'       => trim($request->input('cargo')),
            'UNIDAD'      => trim($request->input('unidad')),
            'EMAIL'       => $email,
            'ORIGEN'      => $origen,
            'ACTIVE'      => $request->boolean('active') ? 1 : 0,
        ];

        $tipoUsuarioActual = Auth::user()->idType
            ?? Auth::user()->idtype
            ?? Auth::user()->IDTYPE
            ?? null;

        if ((int) $tipoUsuarioActual === 1 && $request->filled('idtype')) {
            $datos['IDTYPE'] = $request->integer('idtype');
        }

        if ($request->filled('nuevaPassword')) {
            $datos['PASSWORD'] = Hash::make($request->input('nuevaPassword'));
            $datos['MUST_CHANGE_PASSWORD'] = '1';
        }

        $db->table('users')
            ->where('ID_USER', $idUsuario)
            ->update($datos);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
        ]);
    }

    public function buscarEmpleadoParaUsuario(Request $request)
    {
        $datos = $request->validate([
            'numEmpleado' => ['required', 'string', 'max:20'],
            'origen' => ['required', 'in:secretaría,guardia'],
        ]);

        $numeroEmpleado = trim(explode('-', $datos['numEmpleado'])[0]);
        $esGuardia = $datos['origen'] === 'guardia';

        $conexion = $esGuardia ? 'gnnomina' : 'oracle_secondary';
        $tabla = $esGuardia ? 'EMPLEADOS_PRESTACIONESGN' : 'EMPLEADOS_PRESTACIONES';

        try {
            $empleado = DB::connection($conexion)
                ->table($tabla)
                ->where('NUM_EMPLEADO', $numeroEmpleado)
                ->first();

            if (!$empleado) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el empleado en el origen seleccionado.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'numEmpleado' => $empleado->num_empleado ?? $empleado->NUM_EMPLEADO ?? $numeroEmpleado,
                    'firstName' => $empleado->nombre ?? $empleado->NOMBRE ?? '',
                    'lastName' => $empleado->apellido_paterno ?? $empleado->APELLIDO_PATERNO ?? '',
                    'middleName' => $empleado->apellido_materno ?? $empleado->APELLIDO_MATERNO ?? '',
                    'nivel' => $empleado->nivel ?? $empleado->NIVEL ?? '',
                    'unidad' => $empleado->unidad ?? $empleado->UNIDAD ?? '',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al buscar empleado para alta de usuario', [
                'conexion' => $conexion,
                'tabla' => $tabla,
                'origen' => $datos['origen'],
                'numeroEmpleado' => $numeroEmpleado,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No fue posible consultar el origen seleccionado.',
            ], 503);
        }
    }

    public function listarUsuarios()
    {
        try {
            $usuarios = DB::connection('oracle_primary')
                ->table('users as u')
                ->leftJoin('typeUser as t', 't.idType', '=', 'u.idType')
                ->select([
                    'u.id_user',
                    'u.numEmpleado',
                    'u.firstName',
                    'u.lastName',
                    'u.middleName',
                    'u.nivel',
                    'u.unidad',
                    'u.email',
                    'u.active',
                    'u.idType',
                    'u.origen',
                    't.typeuser as typeuser',
                ])
                ->orderBy('u.id_user', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $usuarios,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al listar usuarios', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error al consultar usuarios.',
            ], 500);
        }
    }

    public function obtenerAniosReporte()
    {
        try {
            $usuario = Auth::user();
            $tipoUsuario = (int) ($usuario->idType ?? $usuario->idtype ?? $usuario->IDTYPE ?? 0);
            $esAdministrador = $tipoUsuario === 1;
            $origen = $esAdministrador ? 'TODOS' : $this->origenReporteDelUsuario();

            $consultaAnios = DB::connection('oracle_primary')->table('registers');
            if (!$esAdministrador) {
                $consultaAnios->whereRaw('UPPER(ORIGEN) = ?', [$origen]);
            }
            $this->filtrarRegistrosPorEnlace($consultaAnios);

            $anios = $consultaAnios
                ->whereNotNull('CREATED_AT')
                ->selectRaw('EXTRACT(YEAR FROM CREATED_AT) AS ANIO')
                ->distinct()
                ->orderByRaw('EXTRACT(YEAR FROM CREATED_AT) DESC')
                ->get()
                ->map(function ($fila) {
                    $valores = array_values((array) $fila);

                    return (int) ($valores[0] ?? 0);
                })
                ->filter(fn($anio) => $anio > 0)
                ->values();

            return response()->json([
                'status' => true,
                'origen' => $origen,
                'data' => $anios,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar años disponibles para reportes', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No fue posible consultar los años del reporte.',
            ], 500);
        }
    }

    public function obtenerReporteRegistros(Request $request)
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        try {
            $usuario = Auth::user();
            $tipoUsuario = (int) ($usuario->idType ?? $usuario->idtype ?? $usuario->IDTYPE ?? 0);
            $esAdministrador = $tipoUsuario === 1;
            $origen = $esAdministrador ? 'TODOS' : $this->origenReporteDelUsuario();
            $columnas = $this->columnasReportePorOrigen($origen);

            $registrosQuery = Register::query();

            if (!$esAdministrador) {
                $registrosQuery->whereRaw('UPPER(ORIGEN) = ?', [$origen]);
            }

            $this->filtrarRegistrosPorEnlace($registrosQuery);

            $registros = $registrosQuery
                ->whereRaw('EXTRACT(YEAR FROM CREATED_AT) = ?', [(int) $datos['anio']])
                ->orderBy('IDREGISTER', 'desc')
                ->get();

            $dataFormateada = $registros->map(function ($registro) {
                $array = $registro->toArray();

                $numEmpleado = trim((string)($registro->NUMEMPLEADO ?? $registro->numempleado ?? ''));

                $pensionados = DB::connection('oracle_primary')
                    ->table('pensiones')
                    ->where('NUMEMPLEADO', $numEmpleado)
                    ->get();

                $nombresPensionados = $pensionados->map(function ($p) {
                    return $p->NOMBREPENSIONADO ?? $p->nombrepensionado ?? $p->NOMBRE_PENSIONADO ?? null;
                })->filter()->implode(', ');

                $array['PENSION'] = $nombresPensionados !== '' ? $nombresPensionados : 'N/A';
                $array['ORIGEN'] = $registro->ORIGEN;

                return $array;
            });

            return response()->json([
                'status' => true,
                'anio' => (int) $datos['anio'],
                'origen' => $origen,
                'columnas' => $columnas,
                'data' => $dataFormateada,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar reporte de registros con pensionados', [
                'user_id' => Auth::id(),
                'anio' => $datos['anio'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No fue posible consultar los registros del reporte.',
            ], 500);
        }
    }

    private function filtrarRegistrosPorEnlace($query, string $columna = 'IDENLACE'): void
    {
        $usuario = Auth::user();
        $tipoUsuario = (int) (
            $usuario->idType
            ?? $usuario->idtype
            ?? $usuario->IDTYPE
            ?? 0
        );

        if ($tipoUsuario !== 3) {
            return;
        }

        $partesNombre = array_filter(array_map('trim', [
            $usuario->firstName ?? $usuario->firstname ?? $usuario->FIRSTNAME ?? '',
            $usuario->lastName ?? $usuario->lastname ?? $usuario->LASTNAME ?? '',
            $usuario->middleName ?? $usuario->middlename ?? $usuario->MIDDLENAME ?? '',
        ]), fn($parte) => $parte !== '');
        $nombreCompleto = preg_replace('/\s+/', ' ', implode(' ', $partesNombre));

        if ($nombreCompleto === '') {
            $query->whereRaw('1 = 0');
            return;
        }

        $nombreNormalizado = strtr(mb_strtoupper($nombreCompleto, 'UTF-8'), [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);
        $nombreBuscado = '%' . $nombreNormalizado . '%';

        $query->whereRaw(
            "TRANSLATE(UPPER(TRIM(REGEXP_REPLACE({$columna}, '[[:space:]]+', ' '))), 'ÁÉÍÓÚÜÑ', 'AEIOUUN') LIKE ?",
            [$nombreBuscado]
        );
    }

    private function origenReporteDelUsuario(): string
    {
        $usuario = Auth::user();
        $origenUsuario = strtolower(trim((string) (
            $usuario->origen
            ?? $usuario->ORIGEN
            ?? ''
        )));

        return in_array($origenUsuario, [
            'sspc',
            'sc',
            'sector central',
            'secretaria',
            'secretaría',
        ], true) ? 'SC' : 'GN';
    }

    private function columnasReportePorOrigen(string $origen): array
    {
        $usuario = Auth::user();
        $tipoUsuario = (int) ($usuario->idType ?? $usuario->idtype ?? $usuario->IDTYPE ?? 0);
        $esAdministrador = $tipoUsuario === 1;

        $comunes = [
            ['key' => 'IDREGISTER', 'label' => 'ID de registro'],
            ['key' => 'NUMEMPLEADO', 'label' => 'Número de empleado'],
            ['key' => 'NOMBRECOMPLETO', 'label' => 'Nombre completo'],
            ['key' => 'UR', 'label' => 'UR'],
            ['key' => 'RFC', 'label' => 'RFC'],
            ['key' => 'CURP', 'label' => 'CURP'],
            ['key' => 'NSS', 'label' => 'NSS'],
            ['key' => 'NIVEL', 'label' => 'Nivel'],
            ['key' => 'IDENLACE', 'label' => 'Enlace'],
            ['key' => 'CUENTAINICIAL', 'label' => 'Cuenta asignada'],
            ['key' => 'IDSTATUS', 'label' => 'Estatus'],
            ['key' => 'CREATED_AT', 'label' => 'Fecha de registro'],
        ];

        $columnas = $comunes;

        if ($esAdministrador) {
            $columnas[] = ['key' => 'ORIGEN', 'label' => 'Origen del registro'];
        }

        if ($origen === 'TODOS' || $origen === 'SC') {
            $columnas[] = ['key' => 'PENSION', 'label' => 'Pensión alimenticia'];
        }

        if ($origen === 'GN') {
            $columnas[] = ['key' => 'PUESTO', 'label' => 'Puesto'];
            $columnas[] = ['key' => 'UBICACION', 'label' => 'Ubicación física'];
            $columnas[] = ['key' => 'CLAVE', 'label' => 'Clave'];
            $columnas[] = ['key' => 'MODALIDAD', 'label' => 'Modalidad'];
        }

        return $columnas;
    }

    public function obtenerTarjetas()
    {
        try {
            $consultaTarjetas = DB::connection('oracle_primary')
                ->table('cards as c')
                ->leftJoin('registers as r', 'r.IDREGISTER', '=', 'c.IDREGISTER')
                ->leftJoin('status as s', 's.idStatus', '=', 'c.idStatus')
                ->select([
                    'c.idCard',
                    'c.IDREGISTER',
                    'c.folioCard',
                    'c.cuenta',
                    'c.idStatus',
                    's.status as status',
                    'c.observaciones',
                    'r.NUMEMPLEADO',
                    'r.NOMBRECOMPLETO',
                    'r.PENSION',
                ]);
            $this->filtrarRegistrosPorEnlace($consultaTarjetas, 'r.IDENLACE');
            $tarjetas = $consultaTarjetas
                ->orderByDesc('c.idCard')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $tarjetas,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al consultar tarjetas: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al consultar tarjetas.',
            ], 500);
        }
    }

    public function obtenerEstatusTarjetas()
    {
        try {
            $estatus = DB::connection('oracle_primary')
                ->table('status')
                ->select(['idStatus', 'status'])
                ->orderBy('idStatus')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $estatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al consultar estatus de tarjetas: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al consultar los estatus.',
            ], 500);
        }
    }

    public function crearTarjetaReemplazo(Request $request)
    {
        $validated = $request->validate([
            'IDREGISTER' => 'required|integer',
            'folioCard' => 'required|string|max:255',
            'cuenta' => 'required|string|max:255',
            'idStatus' => 'required|integer',
            'observaciones' => 'nullable|string',
        ]);

        try {
            $registroExiste = DB::connection('oracle_primary')
                ->table('registers')
                ->where('IDREGISTER', $validated['IDREGISTER'])
                ->exists();

            $estatusExiste = DB::connection('oracle_primary')
                ->table('status')
                ->where('idStatus', $validated['idStatus'])
                ->exists();

            if (!$registroExiste) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el empleado seleccionado.',
                ], 404);
            }

            if (!$estatusExiste) {
                return response()->json([
                    'status' => false,
                    'message' => 'El estatus seleccionado no existe.',
                ], 422);
            }

            $tarjeta = DB::connection('oracle_primary')->transaction(
                function () use ($validated) {
                    $registro = Register::on('oracle_primary')
                        ->findOrFail($validated['IDREGISTER']);

                    $tarjeta = new Card();
                    $tarjeta->IDREGISTER = $validated['IDREGISTER'];
                    $tarjeta->folioCard = $validated['folioCard'];
                    $tarjeta->cuenta = $validated['cuenta'];
                    $tarjeta->idStatus = $validated['idStatus'];
                    $tarjeta->observaciones = $validated['observaciones'] ?? null;
                    $tarjeta->save();

                    $registro->CUENTAINICIAL = $validated['cuenta'];
                    $registro->save();

                    return $tarjeta;
                }
            );

            return response()->json([
                'status' => true,
                'message' => 'Tarjeta de reemplazo registrada correctamente.',
                'data' => $tarjeta,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al registrar tarjeta de reemplazo: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al registrar la tarjeta.',
            ], 500);
        }
    }

    public function actualizarEstatusTarjeta(Request $request, $idCard)
    {
        $validated = $request->validate([
            'idStatus' => 'required|integer',
        ]);

        try {
            $estatusExiste = DB::connection('oracle_primary')
                ->table('status')
                ->where('idStatus', $validated['idStatus'])
                ->exists();

            if (!$estatusExiste) {
                return response()->json([
                    'status' => false,
                    'message' => 'El estatus seleccionado no existe.',
                ], 422);
            }

            $tarjeta = Card::findOrFail($idCard);
            $tarjeta->idStatus = $validated['idStatus'];
            $tarjeta->save();

            return response()->json([
                'status' => true,
                'message' => 'Estatus de tarjeta actualizado correctamente.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'No se encontró la tarjeta.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estatus de tarjeta: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al actualizar el estatus.',
            ], 500);
        }
    }

    public function obtenerDetalleModuloTarjetas($idRegister)
    {
        try {
            $conexion = DB::connection('oracle_primary');

            $empleadoQuery = Register::query()
                ->select([
                    'IDREGISTER',
                    'NOMBRECOMPLETO',
                    'NUMEMPLEADO',
                    'NOMEMPLEADO',
                    'APPEMPLEADO',
                    'APMEMPLEADO',
                    'UR',
                    'RFC',
                    'CURP',
                    'NSS',
                    'NIVEL',
                    'PUESTO',
                    'UBICACION',
                    'IDENLACE',
                    'CLAVE',
                    'CUENTAINICIAL',
                    'MODALIDAD',
                    'IDSTATUS',
                    'ORIGEN',
                    'PENSION',
                    'IMPORTE',
                    'OBSERVACIONINICIAL',
                    'CREATED_AT',
                    'UPDATED_AT',
                ])
                ->where('IDREGISTER', $idRegister);
            $this->filtrarRegistrosPorEnlace($empleadoQuery);
            $empleado = $empleadoQuery->first();

            if (!$empleado) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el empleado.',
                ], 404);
            }

            $pensionados = Pension::where('numEmpleado', '=', $empleado->numempleado)->get();

            $tarjetas = $conexion
                ->table('cards as c')
                ->leftJoin('status as s', 's.idStatus', '=', 'c.idStatus')
                ->select([
                    'c.idCard',
                    'c.IDREGISTER',
                    'c.folioCard',
                    'c.cuenta',
                    'c.idStatus',
                    's.status as status',
                    'c.observaciones',
                    'c.CREATED_AT',
                    'c.UPDATED_AT',
                ])
                ->where('c.IDREGISTER', $idRegister)
                ->orderByDesc('c.idCard')
                ->get();

            $estatus = $conexion
                ->table('status')
                ->select(['idStatus', 'status'])
                ->orderBy('idStatus')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'empleado' => $empleado,
                    'pensionados' => $pensionados,
                    'tarjetas' => $tarjetas,
                    'estatusDisponibles' => $estatus,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar módulo de tarjetas', [
                'idRegister' => $idRegister,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error al consultar la información del empleado y sus tarjetas.',
            ], 500);
        }
    }

    public function guardarObservacionInicial(Request $request)
    {
        $request->validate([
            'idRegister' => ['required'],
            'observacioninicial' => ['nullable', 'string', 'max:1000'],
        ]);

        $idRegister = $request->input('idRegister');
        $observacion = $request->input('observacioninicial');

        try {
            $afectados = DB::connection('oracle_primary')
                ->table('registers')
                ->where('IDREGISTER', $idRegister)
                ->update([
                    'OBSERVACIONINICIAL' => $observacion,
                ]);

            if ($afectados === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el registro especificado.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Observación actualizada correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar la observación inicial: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al guardar la observación.',
            ], 500);
        }
    }
    public function guardarDocumentoAnexo(Request $request)
    {
        $request->validate([
            'idRegister'     => 'required|integer',
            'idTypeDocument' => 'required|integer',
            'archivo'        => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        try {
            $file = $request->file('archivo');

            $path = $file->store('', 'documentos');

            $documento = Document::create([
                'document'       => $path,
                'idTypeDocument' => (int) $request->input('idTypeDocument'),
                'idRegister'     => (int) $request->input('idRegister'),
                'status'         => null,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Documento anexo clasificado y guardado correctamente.',
                'data'    => $documento,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al guardar documento anexo: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Error al guardar el documento: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function obtenerDocumentos($idRegister)
    {
        try {
            $documentos = Document::where('idRegister', $idRegister)
                ->get()
                ->map(function ($doc) {
                    $docId = $doc->idDocument ?? $doc->iddocument ?? $doc->IDDOCUMENT;
                    $doc->url = '/api/gestor/documentos/ver/' . $docId;
                    return $doc;
                });
            return response()->json([
                'status' => true,
                'data'   => $documentos,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener los documentos: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al obtener los documentos.',
            ], 500);
        }
    }

    public function verDocumento($id)
    {
        try {
            $doc = Document::find($id);

            if (!$doc || !Storage::disk('documentos')->exists($doc->document)) {
                return response()->json([
                    'status' => false,
                    'message' => 'El archivo no se encuentra en el servidor.',
                ], 404);
            }

            $filePath = Storage::disk('documentos')->path($doc->document);
            $mimeType = mime_content_type($filePath) ?: 'application/pdf';

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"'
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al visualizar el documento: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al cargar el archivo.',
            ], 500);
        }
    }

    public function eliminarDocumento($idDocument)
    {
        try {
            $documento = Document::findOrFail($idDocument);

            if (Storage::disk('documentos')->exists($documento->document)) {
                Storage::disk('documentos')->delete($documento->document);
            }

            $documento->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Documento eliminado correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar documento: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Error al eliminar el documento.',
            ], 500);
        }
    }
    public function obtenerTiposPersona()
    {
        try {
            $tipos = DB::connection('oracle_primary')->table('typePerson')->get();
            return response()->json([
                'status' => true,
                'data' => $tipos,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar tipos de persona: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error al consultar tipos de persona.',
            ], 500);
        }
    }

    public function obtenerPersonasBeneficiarias($idRegister)
    {
        try {
            $personas = \App\Models\Persona::with('typePerson')
                ->where('idregister', (int) $idRegister)
                ->get()
                ->map(function ($persona) {
                    return [
                        'idperson'     => $persona->idperson ?? $persona->IDPERSON ?? null,
                        'nomPerson'    => $persona->nomperson ?? $persona->NOMPERSON ?? '',
                        'appPerson'    => $persona->appperson ?? $persona->APPPERSON ?? '',
                        'apmPerson'    => $persona->apmperson ?? $persona->APMPERSON ?? '',
                        'curp'         => $persona->curp ?? $persona->CURP ?? '',
                        'rfc'          => $persona->rfc ?? $persona->RFC ?? '',
                        'typePerson'  => [
                            'TypePerson' => $persona->typePerson->TypePerson
                                ?? $persona->typePerson->TYPEPERSON
                                ?? $persona->typePerson->typePerson
                                ?? $persona->typePerson->typeperson
                                ?? 'No especificado'
                        ]
                    ];
                });

            return response()->json([
                'status' => true,
                'data'   => $personas,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar personas beneficiarias: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Error al consultar personas: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function guardarPersonaBeneficiaria(Request $request)
    {
        $validated = $request->validate([
            'idregister'   => 'required|integer',
            'idTypePerson' => 'required|integer',
            'nomPerson'    => 'required|string|max:200',
            'appPerson'    => 'required|string|max:200',
            'apmPerson'    => 'required|string|max:200',
            'curp'         => 'nullable|string|max:18',
            'rfc'          => 'nullable|string|max:13',
        ]);

        try {
            $persona = \App\Models\Persona::create($validated);

            return response()->json([
                'status'  => true,
                'message' => 'Persona registrada correctamente.',
                'data'    => $persona,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al guardar persona: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Error al registrar la persona: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function descargarFormatoPersonaIndividual($idRegister, $idPerson)
    {

        if (!$idRegister || $idRegister === 'undefined' || !$idPerson || $idPerson === '0' || $idPerson === 'undefined') {
            return response()->json([
                'status' => false,
                'message' => 'Los parámetros proporcionados son inválidos.',
            ], 400);
        }

        try {
            $consultaEmpleado = Register::query()->where('IDREGISTER', $idRegister);
            $this->filtrarRegistrosPorEnlace($consultaEmpleado);
            $empleado = $consultaEmpleado->first();

            if (!$empleado) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el empleado o no tiene permisos.',
                ], 404);
            }

            $persona = \App\Models\Persona::where('idregister', $idRegister)
                ->where('idperson', $idPerson)
                ->first();
            Log::info('Parámetros recibidos para descarga:', [
                'persona' => $persona,
            ]);

            if (!$persona) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el registro de la persona.',
                ], 404);
            }

            $templatePath = storage_path('app/templates/formato_fallecimiento.docx');

            if (!file_exists($templatePath)) {
                throw new \Exception('La plantilla de Word "formato_fallecimiento.docx" no existe en el servidor.');
            }

            $templateProcessor = new TemplateProcessor($templatePath);

            $numeroEmpleado = trim((string) ($empleado->numempleado ?? $empleado->NUMEMPLEADO ?? $idRegister));
            $rawImporte = (float) ($empleado->IMPORTE ?? $empleado->importe ?? 0);

            $formatter = new NumeroALetras();
            $importeEnLetra = $formatter->toMoney($rawImporte, 2, 'PESOS', 'CENTAVOS');

            $templateProcessor->setValue('cuenta', $empleado->CUENTAINICIAL ?? $empleado->cuentaInicial ?? $empleado->cuentainicial ?? 'N/A');
            $templateProcessor->setValue('importe', number_format($rawImporte, 2, '.', ','));
            $templateProcessor->setValue('importe_letra', mb_strtoupper($importeEnLetra));

            $templateProcessor->setValue('nombreCompleto', $empleado->nombrecompleto ?? $empleado->nombre ?? 'N/A');
            $templateProcessor->setValue('numEmpleado', $numeroEmpleado);
            $templateProcessor->setValue('enlace', $empleado->idenlace ?? 'NOMBRE DEL ENLACE');

            $nombrePersona = trim(($persona->nomperson ?? '') . ' ' . ($persona->appperson ?? '') . ' ' . ($persona->apmperson ?? ''));

            $templateProcessor->setValue('nombreBeneficiario', $nombrePersona !== '' ? $nombrePersona : '________________________________________');
            $templateProcessor->setValue('curpPersona', $persona->curp ?? 'N/A');
            $templateProcessor->setValue('rfcPersona', $persona->rfc ?? 'N/A');

            $templateProcessor->setValue('dia', date('d'));
            $templateProcessor->setValue('mes', date('m'));
            $templateProcessor->setValue('anio', date('Y'));

            $fileName = "formato_fallecimiento_{$idPerson}_{$numeroEmpleado}.docx";
            $tempFile = storage_path("app/public/{$fileName}");

            $templateProcessor->saveAs($tempFile);

            return response()->download($tempFile)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Error al generar formato Word de la persona individual', [
                'idRegister' => $idRegister,
                'idPerson' => $idPerson,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No se pudo generar el documento Word.',
            ], 500);
        }
    }

    public function descargarLineamientos()
    {
        try {
            if (!Storage::disk('public')->exists('Lineamientos_medida_fin.pdf')) {
                return response()->json([
                    'status' => false,
                    'message' => 'El archivo de lineamientos no se encuentra en el servidor.',
                ], 404);
            }

            $filePath = Storage::disk('public')->path('Lineamientos_medida_fin.pdf');

            return response()->file($filePath, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al visualizar los lineamientos: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al cargar el archivo de lineamientos.',
            ], 500);
        }
    }
    public function exportarReporteExcel(Request $request)
    {
        $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'estatus' => ['nullable', 'string'],
            'enlace' => ['nullable', 'string'],
            'origen' => ['nullable', 'string'],
            'ur' => ['nullable', 'string'],
        ]);

        try {
            $anio = $request->input('anio');
            $usuario = Auth::user();
            $tipoUsuario = (int) ($usuario->idType ?? $usuario->idtype ?? $usuario->IDTYPE ?? 0);
            $esAdministrador = $tipoUsuario === 1;
            $origenUsuario = $esAdministrador ? 'TODOS' : $this->origenReporteDelUsuario();

            $registrosQuery = Register::query();

            if (!$esAdministrador) {
                $registrosQuery->whereRaw('UPPER(ORIGEN) = ?', [$origenUsuario]);
            }

            $this->filtrarRegistrosPorEnlace($registrosQuery);

            $registros = $registrosQuery
                ->whereRaw('EXTRACT(YEAR FROM CREATED_AT) = ?', [(int) $anio])
                ->orderBy('IDREGISTER', 'desc')
                ->get();

            if ($request->filled('estatus')) {
                $registros = $registros->filter(fn($r) => (string)($r->IDSTATUS ?? $r->idstatus) === $request->input('estatus'));
            }
            if ($request->filled('enlace')) {
                $registros = $registros->filter(fn($r) => trim((string)($r->IDENLACE ?? $r->idenlace)) === $request->input('enlace'));
            }
            if ($request->filled('origen')) {
                $registros = $registros->filter(fn($r) => strtoupper(trim((string)($r->ORIGEN ?? $r->origen))) === strtoupper($request->input('origen')));
            }
            if ($request->filled('ur')) {
                $registros = $registros->filter(fn($r) => trim((string)($r->UR ?? $r->ur)) === $request->input('ur'));
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = [
                'ID de registro',
                'Número de empleado',
                'Apellido Paterno',
                'Apellido Materno',
                'Nombre(s)',
                'Nombre Completo',
                'Nombre Recortado (21 car.)',
                'UR',
                'RFC',
                'CURP',
                'NSS',
                'Nivel',
                'Enlace',
                'Cuenta asignada',
                'Estatus',
                'Fecha de registro',
            ];

            if ($esAdministrador) {
                $headers[] = 'Origen del registro';
            }
            $headers[] = 'Pensión alimenticia';

            $sheet->fromArray([$headers], NULL, 'A1');

            $rowNum = 2;
            foreach ($registros as $registro) {
                $numEmpleado = trim((string)($registro->NUMEMPLEADO ?? $registro->numempleado ?? ''));
                $origenReg = strtoupper(trim((string)($registro->ORIGEN ?? $registro->origen ?? 'SC')));

                $nombreDatos = $this->consultarNombresDesdeOrigen($numEmpleado, $origenReg);

                $nombres = trim($nombreDatos['nombre'] ?? '');
                $paterno = trim($nombreDatos['apellido_paterno'] ?? '');
                $materno = trim($nombreDatos['apellido_materno'] ?? '');

                // Construir el nombre completo combinado
                $nombreCompletoTotal = trim("{$paterno} {$materno} {$nombres}");
                if ($nombreCompletoTotal === '') {
                    $nombreCompletoTotal = trim((string)($registro->NOMBRECOMPLETO ?? $registro->nombrecompleto ?? ''));
                }

                // Recortar a 21 caracteres exactos
                $nombreRecortado21 = mb_substr($nombreCompletoTotal, 0, 21);

                $estatusVal = (string)($registro->IDSTATUS ?? $registro->idstatus ?? '');
                $estatusNombre = match ($estatusVal) {
                    '1' => 'Pendiente',
                    '2' => 'Entregada',
                    '3' => 'Sustituida',
                    '4' => 'Cancelada',
                    default => $estatusVal ?: '—'
                };

                $fechaReg = $registro->CREATED_AT ?? $registro->created_at ?? '';
                $fechaFormat = $fechaReg ? date('d/m/Y', strtotime($fechaReg)) : '—';

                $pensionados = DB::connection('oracle_primary')
                    ->table('pensiones')
                    ->where('NUMEMPLEADO', $numEmpleado)
                    ->get();
                $nombresPensionados = $pensionados->map(function ($p) {
                    return $p->NOMBREPENSIONADO ?? $p->nombrepensionado ?? $p->NOMBRE_PENSIONADO ?? null;
                })->filter()->implode(', ');
                $pensionStr = $nombresPensionados !== '' ? $nombresPensionados : 'N/A';

                $rowData = [
                    $registro->IDREGISTER ?? $registro->idregister ?? '',
                    $numEmpleado,
                    $paterno,
                    $materno,
                    $nombres,
                    $nombreCompletoTotal,
                    $nombreRecortado21,
                    $registro->UR ?? $registro->ur ?? '',
                    $registro->RFC ?? $registro->rfc ?? '',
                    $registro->CURP ?? $registro->curp ?? '',
                    $registro->NSS ?? $registro->nss ?? '',
                    $registro->NIVEL ?? $registro->nivel ?? '',
                    $registro->IDENLACE ?? $registro->idenlace ?? '',
                    $registro->CUENTAINICIAL ?? $registro->cuentainicial ?? '',
                    $estatusNombre,
                    $fechaFormat,
                ];

                if ($esAdministrador) {
                    $origenTexto = $origenReg === 'SC' ? 'Sector Central (SSPC)' : ($origenReg === 'GN' ? 'Guardia Nacional' : $origenReg);
                    $rowData[] = $origenTexto;
                }
                $rowData[] = $pensionStr;

                $sheet->fromArray([$rowData], NULL, 'A' . $rowNum);
                $rowNum++;
            }

            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $fileName = "reporte_empleados_{$anio}.xlsx";
            $tempFile = storage_path("app/public/{$fileName}");

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tempFile);

            return response()->download($tempFile)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Error al exportar reporte Excel con columnas adicionales: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error al generar el archivo Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function consultarNombresDesdeOrigen(string $numEmpleado, string $origen)
    {
        $esGuardia = strtoupper($origen) === 'GN';
        $conexion = $esGuardia ? 'gnnomina' : 'oracle_secondary';
        $tabla = $esGuardia ? 'EMPLEADOS_PRESTACIONES_GN' : 'EMPLEADOS_PRESTACIONES';

        try {
            $empleado = DB::connection($conexion)
                ->table($tabla)
                ->where('NUM_EMPLEADO', $numEmpleado)
                ->first();

            if (!$empleado) {
                $tablaAlt = $esGuardia ? 'EMPLEADOS_PRESTACIONESGN' : 'EMPLEADOS_PRESTACIONES';
                $empleado = DB::connection($conexion)
                    ->table($tablaAlt)
                    ->where('NUM_EMPLEADO', $numEmpleado)
                    ->first();
            }

            if ($empleado) {
                return [
                    'nombre' => $empleado->nombre ?? $empleado->NOMBRE ?? '',
                    'apellido_paterno' => $empleado->apellido_paterno ?? $empleado->APELLIDO_PATERNO ?? '',
                    'apellido_materno' => $empleado->apellido_materno ?? $empleado->APELLIDO_MATERNO ?? '',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("No se pudo consultar nombres en {$conexion} para empleado {$numEmpleado}: " . $e->getMessage());
        }

        return [
            'nombre' => '',
            'apellido_paterno' => '',
            'apellido_materno' => '',
        ];
    }
    public function actualizarEstatusDocumento(Request $request, $idDocument)
    {
        $request->validate([
            'status' => 'required|integer|in:0,1',
        ]);

        try {
            $afectados = DB::connection('oracle_primary')
                ->table('documents')
                ->where('IDDOCUMENT', $idDocument)
                ->update([
                    'STATUS' => (int) $request->input('status'),
                    'UPDATED_AT' => now()
                ]);

            if ($afectados === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el documento especificado.',
                ], 404);
            }

            $documentoActualizado = DB::connection('oracle_primary')
                ->table('documents')
                ->where('IDDOCUMENT', $idDocument)
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Estatus del documento actualizado correctamente.',
                'data' => $documentoActualizado,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar estatus de documento: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al actualizar el estatus del documento.',
            ], 500);
        }
    }

public function obtenerDocumentosUsuario($idUser)
{
    try {
        $actas = Acta::where('id_user', $idUser)
            ->get()
            ->map(function ($acta) {
                $id_acta = $acta->id_acta ?? $acta->ID_ACTA ?? $acta->id_Acta;
                
                // CORREGIDO: Debe coincidir exactamente con la ruta de Laravel (/admin/users/documentos/ver/{id})
                // Asegúrate de incluir el prefijo '/api' si tu grupo de rutas lo requiere (ej: /api/admin/...)
                $acta->url = '/api/admin/users/documentos/ver/' . $id_acta;
                
                return $acta;
            });

        return response()->json([
            'status' => true,
            'data'   => $actas,
        ]);
    } catch (\Throwable $e) {
        Log::error('Error al obtener el acta: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Ocurrió un error al obtener el acta.',
        ], 500);
    }
}

public function verDocumentoUsuario($id)
{
    try {
        // CORREGIDO: Usar la misma variable ($acta) de forma consistente
        $acta = Acta::find($id);

        if (!$acta || !Storage::disk('actas')->exists($acta->acta)) {
            return response()->json([
                'status' => false,
                'message' => 'El archivo no se encuentra en el servidor.',
            ], 404);
        }

        $filePath = Storage::disk('actas')->path($acta->acta);
        $mimeType = mime_content_type($filePath) ?: 'application/pdf';

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"'
        ]);
    } catch (\Throwable $e) {
        Log::error('Error al visualizar el documento: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Ocurrió un error al cargar el archivo.',
        ], 500);
    }
}

    public function guardarDocumentoUsuario(Request $request)
    {
        $request->validate([
            'id_user'     => 'required|integer',
            'id_typeActa' => 'required|integer',
            'archivo'     => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        try {
            $file = $request->file('archivo');
            $nombreArchivo = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());

            $file->storeAs('', $nombreArchivo, 'actas');

            $acta = Acta::create([
                'id_user'     => (int) $request->input('id_user'),
                'id_typeActa' => (int) $request->input('id_typeActa'),
                'acta'        => $nombreArchivo,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Acta guardada correctamente.',
                'data'    => $acta,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al guardar acta de usuario: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error al guardar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function eliminarDocumentoUsuario($idDocument)
    {
        try {
            $acta = Acta::findOrFail($idDocument);

            if (Storage::disk('actas')->exists($acta->acta)) {
                Storage::disk('actas')->delete($acta->acta);
            }

            $acta->delete();

            return response()->json([
                'status' => true,
                'message' => 'Acta eliminada correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar acta de usuario: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al eliminar el documento.',
            ], 500);
        }
    }

    public function obtenerTiposActas()
    {
        try {
            $tipos = DB::connection('oracle_primary')
                ->table('type_actas')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $tipos,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar tipos de actas: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error al consultar los tipos de actas.',
            ], 500);
        }
    }
}
