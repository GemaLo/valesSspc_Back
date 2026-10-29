<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Card;
use App\Models\Register;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Yajra\DataTables\Facades\DataTables;

class AdminController extends Controller
{
    public function obtenerRegistrosVales(Request $request)
{
    try {
        $estatus = $request->query('estatus', 'todos');

        $query = Register::query()
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
                'message' => "Carga exitosa. Cuentas actualizadas: {$actualizados}. No encontrados en registros: {$noEncontrados}.",
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
                    continue; // Omitir encabezados
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
                    continue; // Omitir encabezados
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

                // Validar que exista al menos la cuenta y algún identificador clave
                if (!$cuenta || (!$numEmpleado && !$rfcPensionada && !$curpPensionada)) {
                    continue;
                }

                // Consulta para vincular/actualizar la cuenta en registros de pensiones
                // Puedes ajustar los nombres de las columnas de tu base de datos Oracle si difieren
                $consulta = DB::connection('oracle_primary')
                    ->table('pensiones') // O la tabla correspondiente a pensiones/vales
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
                    'cuenta' => $cuenta, // O la columna de cuenta destinada para pensiones
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

        $consultaEmpleado = Register::query()->where('IDREGISTER', $idRegister);
        $this->filtrarRegistrosPorEnlace($consultaEmpleado);
        $empleado = $consultaEmpleado->first();

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
    public function descargarFormatoPensionados($idRegister)
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
                    'message' => 'No se encontró el empleado.',
                ], 404);
            }

            $numeroEmpleado = trim((string) ($empleado->numempleado ?? $empleado->NUMEMPLEADO ?? ''));
            $pensionados = $empleado->pensionados()
                ->orderBy('NOMBREPENSIONADO')
                ->get();

            if ($pensionados->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'El empleado no tiene pensionados registrados.',
                ], 404);
            }

            $pdf = Pdf::loadView('pdf.formato_pensionados', compact('empleado', 'pensionados'));
            $pdf->setPaper('letter', 'landscape');

            return $pdf->stream("pensionados_{$numeroEmpleado}.pdf");
        } catch (\Throwable $e) {
            Log::error('Error al generar formato de pensionados', [
                'idRegister' => $idRegister,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No se pudo generar el PDF de pensionados.',
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
            $tabla = 'EMPLEADOS_VIATICOS_GN';
            $fuente = 'Guardia Nacional';
        } else {
            $conexion = 'oracle_secondary';
            $tabla = 'EMPLEADOS_VIATICOS';
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

    public function users_list_json()
    {
        $query = DB::connection('oracle_primary')
            ->table('users')
            ->join('typeUser', 'typeUser.idType', '=', 'users.idType')
            ->selectRaw(
                'users.ID_USER AS "id_user",
             users.NUMEMPLEADO AS "numempleado",
             users.FIRSTNAME || \' \' || users.LASTNAME || \' \' || users.MIDDLENAME AS "name_complete",
             users.ORIGEN AS "origen",
             typeUser.TYPEUSER AS "typeuser",
             users.UNIDAD AS "unidad",
             users.EMAIL AS "email",
             users.ACTIVE AS "active"'
            )
            ->orderBy('users.ID_USER');

        if (Auth::check()) {
            $usuarioActual = Auth::user();
            $tipoUsuario = (int) (
                $usuarioActual->idType
                ?? $usuarioActual->idtype
                ?? $usuarioActual->IDTYPE
                ?? 0
            );

            if ($tipoUsuario === 3) {
                $idUsuario = $usuarioActual->id_user
                    ?? $usuarioActual->ID_USER
                    ?? null;

                $query->where('users.id_user', $idUsuario);
            } elseif ($tipoUsuario === 2) {
                $unidad = $usuarioActual->unidad
                    ?? $usuarioActual->UNIDAD
                    ?? null;

                if ($unidad) {
                    $query->where('users.unidad', $unidad);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        return DataTables::of($query)
            ->filterColumn('name_complete', function ($query, $keyword) {
                $query->whereRaw(
                    "UPPER(users.FIRSTNAME || ' ' || users.LASTNAME || ' ' || users.MIDDLENAME) LIKE ?",
                    ['%' . strtoupper($keyword) . '%']
                );
            })
            ->editColumn('active', function ($usuario) {
                return (int) $usuario->active === 1
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-danger">Inactivo</span>';
            })
            ->addColumn('btn_edit', 'buttons.button_user')
            ->rawColumns(['btn_edit', 'active'])
            ->toJson();
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
            'numEmpleado' => ['required', 'string', 'max:20'],
            'origen'      => ['required', 'in:secretaría,guardia'],
            'firstName'   => ['required', 'string', 'max:255'],
            'lastName'    => ['required', 'string', 'max:255'],
            'middleName'  => ['nullable', 'string', 'max:255'],
            'nivel'       => ['required', 'string', 'max:255'],
            'unidad'      => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255'],
            'active'      => ['required', 'boolean'],
            'idType'      => ['nullable', 'integer'],
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
            'FIRSTNAME'   => trim($request->input('firstName')),
            'LASTNAME'    => trim($request->input('lastName')),
            'MIDDLENAME'  => trim($request->input('middleName', '')),
            'NIVEL'       => trim($request->input('nivel')),
            'UNIDAD'      => trim($request->input('unidad')),
            'EMAIL'       => $email,
            'ORIGEN'      => $origen,
            'ACTIVE'      => $request->boolean('active') ? 1 : 0,
        ];

        $tipoUsuarioActual = Auth::user()->idType
            ?? Auth::user()->idtype
            ?? Auth::user()->IDTYPE
            ?? null;

        if ((int) $tipoUsuarioActual === 1 && $request->filled('idType')) {
            $datos['IDTYPE'] = $request->integer('idType');
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
            $keys = array_column($columnas, 'key');
            // ORIGEN se conserva para el filtro de la vista, sin mostrarse como columna.
            $keys[] = 'ORIGEN';

            $registros = DB::connection('oracle_primary')
                ->table('registers')
                ->select(array_values(array_unique($keys)));
            if (!$esAdministrador) {
                $registros->whereRaw('UPPER(ORIGEN) = ?', [$origen]);
            }
            $this->filtrarRegistrosPorEnlace($registros);
            $registros = $registros
                ->whereRaw('EXTRACT(YEAR FROM CREATED_AT) = ?', [(int) $datos['anio']])
                ->orderBy('IDREGISTER', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'anio' => (int) $datos['anio'],
                'origen' => $origen,
                'columnas' => $columnas,
                'data' => $registros,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar reporte de registros', [
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

    /**
     * Limita las consultas al enlace autenticado cuando la cuenta es tipo Enlace (idType 3).
     */
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
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ü' => 'U', 'Ñ' => 'N',
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

        if ($origen === 'TODOS') {
            $origenColumna = [['key' => 'ORIGEN', 'label' => 'Origen del registro']];
            $columnasEspecificas = [
                ['key' => 'PENSION', 'label' => 'Pensión alimenticia'],
                ['key' => 'PUESTO', 'label' => 'Puesto'],
                ['key' => 'UBICACION', 'label' => 'Ubicación física'],
                ['key' => 'CLAVE', 'label' => 'Clave'],
                ['key' => 'MODALIDAD', 'label' => 'Modalidad'],
            ];

            return array_merge($comunes, $origenColumna, $columnasEspecificas);
        }

        $especificas = $origen === 'SC'
            ? [['key' => 'PENSION', 'label' => 'Pensión alimenticia']]
            : [
                ['key' => 'PUESTO', 'label' => 'Puesto'],
                ['key' => 'UBICACION', 'label' => 'Ubicación física'],
                ['key' => 'CLAVE', 'label' => 'Clave'],
                ['key' => 'MODALIDAD', 'label' => 'Modalidad'],
            ];

        return array_merge($comunes, $especificas);
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

            $pensionados = $empleado->pensionados()
                ->orderBy('NOMBREPENSIONADO')
                ->get();

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
}







