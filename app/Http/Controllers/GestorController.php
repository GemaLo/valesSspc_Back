<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Register;

class GestorController extends Controller
{
    public function getEmpleados(Request $request)
{
    $numEmpleado = trim($request->input('num_empleado', ''));
    $origen = $request->input('origen', 'ambos'); // 'ambos', 'SC' o 'GN'

    if (empty($numEmpleado)) {
        return response()->json([
            'status' => true,
            'data' => []
        ]);
    }

    $empleadosCentral = collect();
    $empleadosGn = collect();

    // Consultar Sector Central si se seleccionó 'ambos' o 'SC'
    if ($origen === 'ambos' || $origen === 'SC') {
        $empleadosCentral = DB::connection('oracle_secondary')
            ->table('EMPLEADOS_PRESTACIONES')
            ->select('num_empleado', 'nombre', 'apellido_paterno', 'apellido_materno',  'rfc', 'curp', 'nivel', DB::raw("'Sector Central' as origen"))
            ->where('num_empleado', 'like', "%{$numEmpleado}%")
            ->get();
    }

    // Consultar GN si se seleccionó 'ambos' o 'GN'
    if ($origen === 'ambos' || $origen === 'GN') {
        $empleadosGn = DB::connection('oracle_gn')
            ->table('EMPLEADOS_PRESTACIONES_GN')
            ->select('num_empleado', 'nombre', 'rfc', 'curp', 'nivel', DB::raw("'GN' as origen"))
            ->where('num_empleado', 'like', "%{$numEmpleado}%")
            ->get();
    }

    $empleados = $empleadosCentral->concat($empleadosGn);

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

        // Generar archivo CSV descargable
        $filename = "empleados_seleccionados_" . date('Y-m-d_H-i-s') . ".csv";
        
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($seleccionados) {
            $file = fopen('php://output', 'w');
            // Añadir BOM para que Excel reconozca tildes y caracteres especiales UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeceras del CSV
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
    public function guardarCaptura(Request $request)
{
    $empleados = $request->input('empleados', []);

    if (empty($empleados)) {
        return response()->json([
            'status' => false,
            'message' => 'No se recibieron empleados para registrar.'
        ], 400);
    }

    DB::connection('oracle_primary')->beginTransaction();

    try {
        foreach ($empleados as $emp) {
            // Mapeamos los campos del objeto de React a las columnas de tu modelo Register
            Register::create([
                'numEmpleado' => $emp['num_empleado'] ?? null,
                'nomEmpleado' => $emp['nombre'] ?? null,
                'appEmpleado' => trim(($emp['apellido_paterno'] ?? '') . ' ' . ($emp['apellido_materno'] ?? '')),
                'rfc'         => $emp['rfc'] ?? null,
                'curp'        => $emp['curp'] ?? null,
                'unidad'      => $emp['nivel'] ?? null, // O el campo que corresponda a la unidad
                'cargo'       => $emp['cargo'] ?? null,
                'ss'          => $emp['ss'] ?? null,
                'nivel'       => $emp['nivel'] ?? null,
                'ubicacion'   => $emp['domicilio'] ?? null,
                'clave_UR'    => $emp['clave_ur'] ?? null,
                'modalidad'   => $emp['modalidad'] ?? null,
                'idStatus'    => 1 // Ajusta el estatus inicial según tu lógica de negocio
            ]);
        }

        DB::connection('oracle_primary')->commit();

        return response()->json([
            'status' => true,
            'message' => 'Registros guardados correctamente.'
        ]);

    } catch (\Exception $e) {
        DB::connection('oracle_primary')->rollBack();
        
        return response()->json([
            'status' => false,
            'message' => 'Error al guardar en la base de datos: ' . $e->getMessage()
        ], 500);
    }
}
}