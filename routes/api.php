<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\GestorController;
use Illuminate\Http\Request;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::get('/profile', function (Request $request) {
        return response()->json([
            'status' => true,
            'user' => $request->user()
        ]);
    });

    Route::get('/gestor/empleados', [GestorController::class, 'getEmpleados']);
    Route::post('/gestor/exportar-excel', [GestorController::class, 'exportExcel']);
    Route::post('/gestor/guardar-captura', [GestorController::class, 'guardarCaptura']);
    Route::post('/gestor/pensionados/cargar', [GestorController::class, 'cargarPensionados']);

    Route::get('/gestor/vales/registrados', [AdminController::class, 'obtenerRegistrosVales']);
    Route::post('/gestor/vales/actualizar-estatus', [AdminController::class, 'actualizarEstatusVale']);
    Route::post('/gestor/vales/cargar-tarjetas', [AdminController::class, 'cargarTarjetas']);
    Route::get('/gestor/vales/descargar-formatos/{idRegister}', [AdminController::class, 'descargarFormatos']);
    Route::get('/gestor/vales/descargar-formatos-pensionados/{idRegister}', [AdminController::class, 'descargarFormatoPensionados']);
    // Route::get('/gestor/vales/descargar-formatos/{idRegister}',[AdminController::class, 'descargarFormatos']);
    Route::get('/admin/users', [AdminController::class, 'listarUsuarios']);
    Route::get('/admin/users/empleado', [AdminController::class, 'buscarEmpleadoParaUsuario']);
    Route::post('/admin/users', [AdminController::class, 'crearUsuario']);
    Route::put('/admin/users/{id_user}', [AdminController::class, 'actualizarUsuario']);

    Route::get('/admin/reportes/anios', [AdminController::class, 'obtenerAniosReporte']);
    Route::get('/admin/reportes/registros', [AdminController::class, 'obtenerReporteRegistros']);

    Route::get('/gestor/tarjetas',[AdminController::class, 'obtenerTarjetas']);
    Route::get('/gestor/tarjetas/estatus',[AdminController::class, 'obtenerEstatusTarjetas']);
    Route::post('/gestor/tarjetas',[AdminController::class, 'crearTarjetaReemplazo']);
    Route::patch('/gestor/tarjetas/{idCard}/estatus',[AdminController::class, 'actualizarEstatusTarjeta']);
    Route::get('/gestor/tarjetas/empleado/{idRegister}',[AdminController::class, 'obtenerDetalleModuloTarjetas']);
    Route::post('gestor/vales/cargar-pensiones', [AdminController::class, 'cargarPensiones']);
    Route::post('/gestor/vales/observacion', [AdminController::class, 'guardarObservacionInicial']);
});
