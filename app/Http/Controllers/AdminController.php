<?php

namespace App\Http\Controllers;

use App\Models\Register;
use App\Models\Card;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf; 

class AdminController extends Controller
{
    public function obtenerRegistrosVales(Request $request)
    {
        $estatus = $request->query('estatus', 'todos');
        $query = Register::query();
        if ($estatus !== 'todos') {
            $query->where('idStatus', $estatus);
        }
        $registros = $query->orderBy('idRegister', 'desc')->get();
        return response()->json([
            'status' => true,
            'data' => $registros
        ]);
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
    $path = $file->getRealPath();
    $extension = strtolower($file->getClientOriginalExtension());

    $actualizados = 0;
    $noEncontrados = 0;
    $filas = [];

    // 1. Lectura del archivo (CSV / TXT)
    if ($extension === 'csv' || $extension === 'txt') {
        $contenido = file_get_contents($path);
        $delimitador = (substr_count($contenido, ';') > substr_count($contenido, ',')) ? ';' : ',';

        if (($handle = fopen($path, 'r')) !== FALSE) {
            $rowNumber = 0;
            while (($data = fgetcsv($handle, 1000, $delimitador)) !== FALSE) {
                $rowNumber++;
                if ($rowNumber === 1) continue; // Omitir encabezados

                $filas[] = [
                    'cuenta' => isset($data[1]) ? trim($data[1]) : null,
                    'rfc'    => isset($data[3]) ? trim(strtoupper($data[3])) : null,
                    'curp'   => isset($data[4]) ? trim(strtoupper($data[4])) : null,
                ];
            }
            fclose($handle);
        }
    } 
    // Si usas Excel nativo (.xlsx) con Maatwebsite/Excel
    else {
        $dataExcel = \Maatwebsite\Excel\Facades\Excel::toArray([], $file);
        if (!empty($dataExcel[0])) {
            foreach ($dataExcel[0] as $index => $row) {
                if ($index === 0) continue;
                $filas[] = [
                    'cuenta' => isset($row[1]) ? trim($row[1]) : null,
                    'rfc'    => isset($row[3]) ? trim(strtoupper($row[3])) : null,
                    'curp'   => isset($row[4]) ? trim(strtoupper($row[4])) : null,
                ];
            }
        }
    }

    DB::connection('oracle_primary')->beginTransaction();

try {
    foreach ($filas as $fila) {
        $cuenta = $fila['cuenta'];
        $rfc    = $fila['rfc'];
        $curp   = $fila['curp'];

        if (!$cuenta || (!$rfc && !$curp)) {
            continue;
        }

        $filasAfectadas = DB::connection('oracle_primary')
            ->table('registers')
            ->where(function ($query) use ($rfc, $curp) {
                if ($rfc) {
                    $query->orWhere('rfc', $rfc);
                }
                if ($curp) {
                    $query->orWhere('curp', $curp);
                }
            })
            ->update(['cuentainicial' => $cuenta]);

        if ($filasAfectadas > 0) {
            $actualizados++;
            \Log::info("Cuenta {$cuenta} asignada exitosamente a RFC: {$rfc} / CURP: {$curp}");
        } else {
            $noEncontrados++;
            \Log::info("No se encontró registro para actualizar con RFC: {$rfc} / CURP: {$curp}");
        }
    }

    DB::connection('oracle_primary')->commit();

} catch (\Exception $e) {
    DB::connection('oracle_primary')->rollBack();
    \Log::error("Error crítico al actualizar tarjetas en Oracle: " . $e->getMessage());

    return response()->json([
        'status' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ], 500);
}

return response()->json([
    'status' => true,
    'message' => "Carga exitosa. Cuentas actualizadas: {$actualizados}. No encontrados en BD: {$noEncontrados}."
]);
}
public function descargarFormatos($idRegister)
    {
        if (!$idRegister || $idRegister === 'undefined') {
            return response()->json([
                'status' => false,
                'message' => 'El ID del registro es inválido.'
            ], 400);
        }

        $empleado = Register::where('idRegister', $idRegister)
            ->orWhere('idregister', $idRegister)
            ->first();

        if (!$empleado) {
            return response()->json([
                'status' => false,
                'message' => 'No se encontró el registro en la base de datos.'
            ], 404);
        }

        $pdf = Pdf::loadView('pdf.formatos_vales', compact('empleado'));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream("formatos_vales_{$empleado->numEmpleado}.pdf");
    }
}
