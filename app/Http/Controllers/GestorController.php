<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Register;
use App\Models\Pension;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GestorController extends Controller
{
    public function getEmpleados(Request $request)
    {
        $numEmpleado = trim($request->input('num_empleado', ''));
        $origen = $request->input('origen', 'ambos');

        if (empty($numEmpleado)) {
            return response()->json([
                'status' => true,
                'data' => []
            ]);
        }

        $empleadosCentral = collect();
        $empleadosGn = collect();

        if ($origen === 'ambos' || $origen === 'SC') {
            $empleadosCentral = DB::connection('oracle_secondary')
                ->table('EMPLEADOS_PRESTACIONES')
                ->select(
                    'num_empleado',
                    'nombre',
                    'apellido_paterno',
                    'apellido_materno',
                    'rfc',
                    'curp',
                    'unidad',
                    'nivel',
                    DB::raw("'Sector Central' as origen")
                )
                ->where('num_empleado', 'like', "%{$numEmpleado}%")
                ->get();
        }

        if ($origen === 'ambos' || $origen === 'GN') {
            $empleadosGn = DB::connection('oracle_gn')
                ->table('EMPLEADOS_PRESTACIONES_GN')
                ->select(
                    'num_empleado',
                    'nombre',
                    'rfc',
                    'curp',
                    'unidad',
                    'nivel',
                    DB::raw("'GN' as origen")
                )
                ->where('num_empleado', 'like', "%{$numEmpleado}%")
                ->get();
        }

        $empleados = $empleadosCentral->concat($empleadosGn);

        if ($empleados->isNotEmpty()) {
            $numerosEmpleado = $empleados
                ->map(fn($empleado) => trim((string) ($empleado->num_empleado ?? $empleado->NUM_EMPLEADO ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $resumenesPension = DB::connection('oracle_primary')
                ->table('registers')
                ->select('NUMEMPLEADO', 'ORIGEN', 'PENSION')
                ->whereIn('NUMEMPLEADO', $numerosEmpleado)
                ->whereYear('CREATED_AT', now()->year)
                ->orderByDesc('UPDATED_AT')
                ->get();

            $pensionPorEmpleado = [];
            foreach ($resumenesPension as $resumen) {
                $codigoOrigen = strtoupper(trim((string) ($resumen->ORIGEN ?? '')));
                $numero = strtoupper(trim((string) ($resumen->NUMEMPLEADO ?? '')));
                $clave = $codigoOrigen . '|' . $numero;
                if (!array_key_exists($clave, $pensionPorEmpleado)) {
                    $pensionPorEmpleado[$clave] = trim((string) ($resumen->PENSION ?? ''));
                }
            }

            $empleados = $empleados->map(function ($empleado) use ($pensionPorEmpleado) {
                $origen = strtoupper(trim((string) ($empleado->origen ?? $empleado->ORIGEN ?? '')));
                $codigoOrigen = in_array($origen, ['SC', 'SECTOR CENTRAL'], true) ? 'SC' : 'GN';
                $numero = strtoupper(trim((string) ($empleado->num_empleado ?? $empleado->NUM_EMPLEADO ?? '')));
                $clave = $codigoOrigen . '|' . $numero;
                $empleado->pension = $pensionPorEmpleado[$clave] ?? 'N/A';
                return $empleado;
            });
        }

        return response()->json([
            'status' => true,
            'data' => $empleados
        ]);
    }
    public function exportExcel(Request $request)
    {
        $idsCentral = $request->input('ids_central', []);
        $idsGn = $request->input('ids_gn', []);

        $seleccionados = collect();

        if (!empty($idsCentral)) {
            $central = DB::connection('oracle_secondary')
                ->table('empleados')
                ->whereIn('id', $idsCentral)
                ->select('id', 'nombre', 'rfc', 'curp', 'nivel', DB::raw("'Sector Central' as origen"))
                ->get();
            $seleccionados = $seleccionados->concat($central);
        }

        if (!empty($idsGn)) {
            $gn = DB::connection('oracle_gn')
                ->table('empleados_gn')
                ->whereIn('id', $idsGn)
                ->select('id', 'nombre', 'rfc', 'curp', 'nivel', DB::raw("'GN' as origen"))
                ->get();
            $seleccionados = $seleccionados->concat($gn);
        }

        $filename = "empleados_seleccionados_" . date('Y-m-d_H-i-s') . ".csv";

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () use ($seleccionados) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['ID', 'Nombre', 'RFC', 'CURP', 'Nivel', 'Origen']);

            foreach ($seleccionados as $emp) {
                fputcsv($file, [
                    $emp->id,
                    $emp->nombre,
                    $emp->rfc,
                    $emp->curp,
                    $emp->nivel,
                    $emp->origen
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
    public function cargarPensionados(Request $request)
{
    $datos = $request->validate([
        'pensionados' => ['required', 'array', 'min:1', 'max:10000'],
        'pensionados.*.numEmpleado' => ['required', 'string', 'max:30'],
        'pensionados.*.nomEmpleado' => ['nullable', 'string', 'max:255'],
        'pensionados.*.nombrePensionado' => ['required', 'string', 'max:255'],
        'pensionados.*.curp' => ['nullable', 'string', 'max:30'],
        'pensionados.*.rfc' => ['nullable', 'string', 'max:20'],
        'pensionados.*.porcentaje' => ['required', 'integer', 'min:0', 'max:100'],
    ]);

    $usuario = $request->user();
    $origenUsuario = strtolower(trim((string) (
        $usuario->origen ?? $usuario->ORIGEN ?? ''
    )));
    $origen = in_array($origenUsuario, [
        'sc', 'sspc', 'sector central', 'secretaria', 'secretaría',
    ], true) ? 'SC' : 'GN';

    $grupos = collect($datos['pensionados'])
        ->map(function (array $pensionado) {
            $pensionado['numEmpleado'] = trim($pensionado['numEmpleado']);
            $pensionado['nomEmpleado'] = trim((string) ($pensionado['nomEmpleado'] ?? ''));
            $pensionado['nombrePensionado'] = trim($pensionado['nombrePensionado']);
            // Limpiamos y convertimos a mayúsculas de forma segura si vienen vacíos
            $pensionado['curp'] = strtoupper(trim((string) ($pensionado['curp'] ?? '')));
            $pensionado['rfc'] = strtoupper(trim((string) ($pensionado['rfc'] ?? '')));
            return $pensionado;
        })
        ->groupBy(fn(array $pensionado) => strtoupper($pensionado['numEmpleado']));

    $empleadosActualizados = 0;
    $registrosPensionActualizados = 0;
    $pensionadosGuardados = 0;
    $sinRegistro = [];
    $sinNombre = [];

    try {
        DB::connection('oracle_primary')->transaction(function () use (
            $grupos,
            $origen,
            &$empleadosActualizados,
            &$registrosPensionActualizados,
            &$pensionadosGuardados,
            &$sinRegistro,
            &$sinNombre
        ) {
            foreach ($grupos as $numeroEmpleado => $filas) {
                $empleados = DB::connection('oracle_primary')
                    ->table('registers')
                    ->select([
                        'IDREGISTER',
                        'NOMBRECOMPLETO',
                        'NOMEMPLEADO',
                        'APPEMPLEADO',
                        'APMEMPLEADO',
                        'CUENTAINICIAL',
                    ])
                    ->whereRaw('UPPER(TRIM(NUMEMPLEADO)) = ?', [$numeroEmpleado])
                    ->whereRaw('UPPER(TRIM(ORIGEN)) = ?', [$origen])
                    ->whereYear('CREATED_AT', now()->year)
                    ->get();

                if ($empleados->isEmpty()) {
                    $sinRegistro[] = $numeroEmpleado;
                    continue;
                }

                $empleado = $empleados->first();
                $nombreEmpleado = '';

                // Puede haber varios registros del mismo empleado; toma el primero que tenga nombre.
                foreach ($empleados as $empleadoCandidato) {
                    $nombreCandidato = trim((string) ($empleadoCandidato->NOMBRECOMPLETO ?? ''));
                    if ($nombreCandidato === '') {
                        $nombreCandidato = trim(implode(' ', array_filter([
                            $empleadoCandidato->NOMEMPLEADO ?? '',
                            $empleadoCandidato->APPEMPLEADO ?? '',
                            $empleadoCandidato->APMEMPLEADO ?? '',
                        ], fn($parte) => trim((string) $parte) !== '')));
                    }

                    if ($nombreCandidato !== '') {
                        $nombreEmpleado = $nombreCandidato;
                        $empleado = $empleadoCandidato;
                        break;
                    }
                }

                if ($nombreEmpleado === '') {
                    foreach ($filas as $fila) {
                        $nombreArchivo = trim((string) ($fila['nomEmpleado'] ?? ''));
                        if ($nombreArchivo !== '') {
                            $nombreEmpleado = $nombreArchivo;
                            break;
                        }
                    }
                }
                if ($nombreEmpleado === '') {
                    $sinNombre[] = $numeroEmpleado;
                    continue;
                }

                $cuenta = trim((string) ($empleado->CUENTAINICIAL ?? '')) ?: 'N/A';

                // La carga reemplaza los detalles anteriores para evitar duplicarlos.
                Pension::query()->where('numEmpleado', $numeroEmpleado)->delete();

                // Usamos la CURP si existe; si está vacía, usamos el nombre del pensionado para evitar colisiones de filas únicas
                $filasUnicas = $filas->unique(fn(array $fila) =>
                    ($fila['curp'] !== '' ? $fila['curp'] : $fila['nombrePensionado']) . '|' . $fila['porcentaje']
                )->values();

                foreach ($filasUnicas as $fila) {
                    Pension::create([
                        'numEmpleado' => $numeroEmpleado,
                        'nomEmpleado' => $nombreEmpleado,
                        'nombrePensionado' => $fila['nombrePensionado'],
                        'curp' => $fila['curp'] !== '' ? $fila['curp'] : null,
                        'rfc' => $fila['rfc'] !== '' ? $fila['rfc'] : null,
                        'cuenta' => $cuenta,
                        'porcentaje' => (int) $fila['porcentaje'],
                    ]);
                }

                // Conteo robusto: cuenta por CURP única, o por Nombre de pensionado si la CURP está vacía
                $cantidadPensionados = $filasUnicas->map(fn($fila) =>
                    $fila['curp'] !== '' ? $fila['curp'] : $fila['nombrePensionado']
                )->unique()->count();

                $resumen = 'SI (' . $cantidadPensionados . ' pensionado'
                    . ($cantidadPensionados === 1 ? '' : 's') . ')';

                $registrosActualizados = DB::connection('oracle_primary')
                    ->table('registers')
                    ->whereRaw('UPPER(TRIM(NUMEMPLEADO)) = ?', [$numeroEmpleado])
                    ->whereRaw('UPPER(TRIM(ORIGEN)) = ?', [$origen])
                    ->whereYear('CREATED_AT', now()->year)
                    ->update(['PENSION' => $resumen]);

                $resumenVerificado = DB::connection('oracle_primary')
                    ->table('registers')
                    ->whereRaw('UPPER(TRIM(NUMEMPLEADO)) = ?', [$numeroEmpleado])
                    ->whereRaw('UPPER(TRIM(ORIGEN)) = ?', [$origen])
                    ->whereYear('CREATED_AT', now()->year)
                    ->where('PENSION', $resumen)
                    ->exists();

                if (!$resumenVerificado) {
                    throw new \RuntimeException(
                        'No se pudo actualizar PENSION en REGISTERS para el empleado ' . $numeroEmpleado . '.'
                    );
                }

                $registrosPensionActualizados += $registrosActualizados;
                $empleadosActualizados++;
                $pensionadosGuardados += $filasUnicas->count();
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Carga de pensionados completada.',
            'empleadosActualizados' => $empleadosActualizados,
            'registrosPensionActualizados' => $registrosPensionActualizados,
            'pensionadosGuardados' => $pensionadosGuardados,
            'sinRegistro' => $sinRegistro,
            'sinNombre' => $sinNombre,
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'status' => false,
            'message' => 'Ocurrió un error al guardar los pensionados.',
        ], 500);
    }
}

    public function guardarCaptura(Request $request)
    {
        $empleados = $request->input('empleados', []);

        if (!is_array($empleados) || count($empleados) === 0) {
            return response()->json([
                'status' => false,
                'message' => 'No se recibieron empleados para registrar.',
            ], 400);
        }

        if (count($empleados) > 5000) {
            return response()->json([
                'status' => false,
                'message' => 'El archivo supera el límite de 5,000 registros por carga.',
            ], 422);
        }

        $registros = [];
        $errores = [];

        foreach ($empleados as $index => $emp) {
            if (!is_array($emp)) {
                $errores[] = 'La fila ' . ($index + 1) . ' no tiene un formato válido.';
                continue;
            }

            $nombreCompleto = trim((string) ($emp['nombre_completo'] ?? ''));

            if ($nombreCompleto === '') {
                $nombreCompleto = trim(implode(' ', array_filter([
                    $emp['nombre'] ?? '',
                    $emp['apellido_paterno'] ?? '',
                    $emp['apellido_materno'] ?? '',
                ], fn($valor) => trim((string) $valor) !== '')));
            }

            $ur = trim((string) (
                $emp['ur']
                ?? $emp['unidad']
                ?? $emp['clave_ur']
                ?? ''
            ));

            $rfc = strtoupper(trim((string) ($emp['rfc'] ?? '')));
            $curp = strtoupper(trim((string) ($emp['curp'] ?? '')));

            if ($nombreCompleto === '' || $ur === '' || $rfc === '' || $curp === '') {
                $errores[] = 'La fila ' . ($index + 1)
                    . ' requiere nombre, UR, RFC y CURP.';
                continue;
            }

            $origenEntrada = strtoupper(trim((string) ($emp['origen'] ?? '')));
            $origen = in_array($origenEntrada, ['SC', 'SECTOR CENTRAL'], true)
                ? 'SC'
                : 'GN';

            $valorNullable = static function ($valor): ?string {
                if ($valor === null || trim((string) $valor) === '') {
                    return null;
                }

                return trim((string) $valor);
            };

            $registros[] = [
                'NOMBRECOMPLETO' => $nombreCompleto,
                'NUMEMPLEADO' => $valorNullable($emp['num_empleado'] ?? null),
                'NOMEMPLEADO' => $valorNullable($emp['nombre'] ?? null),
                'APPEMPLEADO' => $valorNullable($emp['apellido_paterno'] ?? null),
                'APMEMPLEADO' => $valorNullable($emp['apellido_materno'] ?? null),
                'UR' => $ur,
                'RFC' => $rfc,
                'CURP' => $curp,
                'NSS' => $valorNullable($emp['nss'] ?? $emp['ss'] ?? null),
                'NIVEL' => $valorNullable($emp['nivel'] ?? null),
                'PUESTO' => $valorNullable($emp['puesto'] ?? $emp['cargo'] ?? null),
                'UBICACION' => $valorNullable($emp['ubicacion'] ?? $emp['domicilio'] ?? null),
                'IDENLACE' => $valorNullable($emp['enlace'] ?? null),
                'CLAVE' => $valorNullable($emp['clave'] ?? $emp['clave_ur'] ?? null),
                'CUENTAINICIAL' => $valorNullable($emp['cuenta_inicial'] ?? null),
                'MODALIDAD' => $valorNullable($emp['modalidad'] ?? null),
                'IDSTATUS' => 1,
                'ORIGEN' => $origen,
                'PENSION' => $valorNullable($emp['pension'] ?? null),
            ];
        }

        if (!empty($errores)) {
            return response()->json([
                'status' => false,
                'message' => 'Hay filas incompletas. No se guardó ningún registro.',
                'errors' => array_slice($errores, 0, 20),
            ], 422);
        }

        try {
            DB::connection('oracle_primary')->transaction(function () use ($registros) {
                foreach ($registros as $registro) {
                    Register::create($registro);
                }
            });

            return response()->json([
                'status' => true,
                'message' => 'Registros guardados correctamente.',
                'total' => count($registros),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al guardar los registros.',
            ], 500);
        }
    }
}









