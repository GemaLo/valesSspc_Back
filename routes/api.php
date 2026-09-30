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

    Route::get('/gestor/vales/registrados', [AdminController::class, 'obtenerRegistrosVales']);
    Route::post('/gestor/vales/actualizar-estatus', [AdminController::class, 'actualizarEstatusVale']);

});