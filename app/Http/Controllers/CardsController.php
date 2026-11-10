<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Card;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Validator;
use Exception;

class CardsController extends Controller
{
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Por favor, sube un archivo Excel válido (.xlsx, .xls).',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            
            // Leemos el archivo directamente como array de la primera hoja
            $data = Excel::toArray([], $file);

            if (empty($data) || empty($data[0])) {
                return response()->json(['message' => 'El archivo Excel está vacío.'], 400);
            }

            $rows = $data[0]; // Arreglo de filas de la primera hoja
            $insertedCards = [];

            // Omitimos la primera fila (índice 0, que es la cabecera "Cuenta")
            $dataRows = array_slice($rows, 1);

            foreach ($dataRows as $row) {
                // Obtenemos el primer valor disponible de la fila de forma segura
                $cuenta = is_array($row) ? (reset($row) ?: null) : null;

                if (!empty($cuenta)) {
                    $card = Card::create([
                        'cuenta'        => trim((string)$cuenta),
                        'folioCard'     => null,
                        'idRegister'    => null,
                        'idStatus'      => 1,
                        'observaciones' => 'Carga masiva inicial'
                    ]);

                    $insertedCards[] = [
                        'cuenta'    => $card->cuenta,
                        'folioCard' => 'N/A'
                    ];
                }
            }

            if (empty($insertedCards)) {
                return response()->json([
                    'message' => 'No se encontraron registros válidos. Verifica que la columna tenga datos.'
                ], 400);
            }

            return response()->json([
                'message' => '¡Tarjetas cargadas y registradas exitosamente!',
                'tarjetas' => $insertedCards
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}