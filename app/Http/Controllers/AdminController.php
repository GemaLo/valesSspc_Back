<?php

namespace App\Http\Controllers;

use App\Models\Register;
use App\Models\Card;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminController extends Controller
{
public function obtenerRegistrosVales(Request $request)
{
    try {
        $estatus = $request->query('estatus', 'todos');
        
        // Asumiendo que la columna de fecha en Oracle/Eloquent es CREATED_AT o FECHA
        $query = Register::query()
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
    'CREATED_AT',
    'UPDATED_AT',
])
            ->whereYear('CREATED_AT', now()->year);

        // Solo filtra por estatus si no es 'todos'
        if ($estatus !== 'todos' && $estatus !== null) {
            $query->where('IDSTATUS', $estatus);
        }

        $registros = $query->orderBy('IDREGISTER', 'desc')->get();

        return response()->json([
            'status' => true,
            'data' => $registros
        ]);
    } catch (\Exception $e) {
        \Log::error("Error en obtenerRegistrosVales: " . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Error al consultar registros: ' . $e->getMessage()
        ], 500);
    }
}

    public function actualizarEstatusVale(Request $request)
    {
        $request->validate([
            'idRegister' => 'required',
            'idStatus' => 'required|integer|in:1,2'
        ]);

        try {
            $registro = Register::on('oracle_primary')->findOrFail($request->idRegister);
            $registro->idStatus = $request->idStatus;
            $registro->save();

            return response()->json([
                'status' => true,
                'message' => 'Estatus actualizado correctamente.'
            ]);
        } catch (\Exception $e) {
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
                throw new \RuntimeException('No se pudo leer el archivo.');
            }

            $numeroFila = 0;

            while (($data = fgetcsv($handle, 0, $delimitador)) !== false) {
                if ($numeroFila++ === 0) {
                    continue; // Omitir encabezados
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
                    continue; // Omitir encabezados
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
            &$actualizados,
            &$noEncontrados
        ) {
            foreach ($filas as $fila) {
                $cuenta = $fila['cuenta'];
                $rfc = $fila['rfc'];
                $curp = $fila['curp'];

                if (!$cuenta || (!$rfc && !$curp)) {
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

                $afectados = $consulta->update([
                    'CUENTAINICIAL' => $cuenta,
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
            'message' => "Carga exitosa. Cuentas actualizadas: {$actualizados}. No encontrados en BD: {$noEncontrados}.",
            'actualizados' => $actualizados,
            'noEncontrados' => $noEncontrados,
        ]);
    } catch (\Throwable $e) {
        Log::error('Error al actualizar tarjetas en Oracle: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
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

    $empleado = Register::find($idRegister);

    if (!$empleado) {
        return response()->json([
            'status' => false,
            'message' => 'No se encontró el registro en la base de datos.',
        ], 404);
    }

    $pdf = Pdf::loadView('pdf.formatos_vales', compact('empleado'));
    $pdf->setPaper('letter', 'portrait');

    $numeroEmpleado =
        $empleado->NUMEMPLEADO
        ?? $empleado->numempleado
        ?? $empleado->numEmpleado
        ?? $idRegister;

    return $pdf->stream("formatos_vales_{$numeroEmpleado}.pdf");
}
}