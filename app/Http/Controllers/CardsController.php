<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Card;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

            $data = Excel::toArray(new class implements ToCollection {
                public function collection(Collection $collection): void {}
            }, $file);

            if (empty($data) || empty($data[0])) {
                return response()->json(['message' => 'El archivo Excel está vacío.'], 400);
            }

            $rows = $data[0];
            $insertedCards = [];

            $dataRows = array_slice($rows, 1);

            foreach ($dataRows as $row) {
                $cuenta = is_array($row) ? (reset($row) ?: null) : null;

                if (!empty($cuenta)) {
                    $card = Card::create([
                        'cuenta'        => trim((string)$cuenta),
                        'foliocard'     => null,
                        'idregister'    => null,
                        'idstatus'      => 1,
                        'observaciones' => null
                    ]);

                    $insertedCards[] = [
                        'cuenta'    => $card->cuenta,
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
    public function obtenerTarjetasPorAnio(Request $request)
    {
        $request->validate([
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        try {
            $anio = $request->input('anio');

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
                    'c.CREATED_AT',
                    'r.NUMEMPLEADO',
                    'r.NOMBRECOMPLETO',
                    'r.PENSION',
                ]);

            $this->filtrarRegistrosPorEnlace($consultaTarjetas, 'r.IDENLACE');

            if (!empty($anio)) {
                $consultaTarjetas->whereYear('c.CREATED_AT', $anio);
            }

            $tarjetas = $consultaTarjetas->orderByDesc('c.idCard')->get();

            return response()->json([
                'status' => true,
                'data' => $tarjetas,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al consultar tarjetas por año: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error al consultar tarjetas.',
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
}
