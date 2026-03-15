<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\VentaQueryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Login (público)
Route::post('/login', [AuthController::class, 'login']);

// Logout (requiere JWT)
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');

// Rutas protegidas con JWT
Route::middleware('auth:api')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Productos (proxy a Flask)
    Route::get('/productos', [ProductoController::class, 'listar']);
    Route::get('/productos/{id}/stock', [ProductoController::class, 'verificarStock']);

    // Ventas (proxy a Express, flujo con validación de stock)
    Route::post('/ventas', [VentaController::class, 'registrar']);
    Route::get('/ventas', [VentaQueryController::class, 'listar']);
    Route::get('/ventas/fecha/{fecha}', [VentaQueryController::class, 'porFecha']);
    Route::get('/ventas/usuario/{usuario}', [VentaQueryController::class, 'porUsuario']);
});
