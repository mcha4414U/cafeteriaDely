<?php

use App\Http\Controllers\CompraController;
use App\Http\Controllers\InventarioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'activo', 'auth.session'])->group(function () {
    Route::middleware('permiso:compras.gestionar')->group(function () {
        Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
        Route::get('/compras/crear', [CompraController::class, 'crear'])->name('compras.crear');
        Route::post('/compras', [CompraController::class, 'guardar'])->name('compras.guardar');
        Route::get('/compras/{compra}', [CompraController::class, 'mostrar'])->name('compras.mostrar');
    });
    Route::middleware('permiso:inventario.gestionar')->group(function () {
        Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
        Route::get('/inventario/movimientos', [InventarioController::class, 'movimientos'])->name('inventario.movimientos');
    });
});
