<?php

namespace App\Http\Controllers;

use App\Models\Register;
use App\Models\Card;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
}
