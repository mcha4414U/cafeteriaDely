<?php

use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'activo', 'auth.session'])->group(function () {
    Route::post('/productos/{producto}/ajustar-stock', [ProductoController::class, 'ajustarStock'])->middleware('permiso:inventario.gestionar')->name('productos.ajustar-stock');
    foreach (['categorias', 'clientes', 'proveedores'] as $modulo) {
        $consulta = $modulo === 'clientes' ? 'clientes.consultar' : $modulo.'.gestionar';
        $creacion = $modulo === 'clientes' ? 'clientes.registrar' : $modulo.'.gestionar';
        Route::get('/'.$modulo, [CatalogoController::class, 'index'])->defaults('modulo', $modulo)->middleware('permiso:'.$consulta)->name($modulo.'.index');
        Route::get('/'.$modulo.'/crear', [CatalogoController::class, 'crear'])->defaults('modulo', $modulo)->middleware('permiso:'.$creacion)->name($modulo.'.crear');
        Route::post('/'.$modulo, [CatalogoController::class, 'guardar'])->defaults('modulo', $modulo)->middleware('permiso:'.$creacion)->name($modulo.'.guardar');
        Route::get('/'.$modulo.'/{registro}/editar', [CatalogoController::class, 'editar'])->defaults('modulo', $modulo)->middleware('permiso:'.$modulo.'.gestionar')->name($modulo.'.editar');
        Route::put('/'.$modulo.'/{registro}', [CatalogoController::class, 'actualizar'])->defaults('modulo', $modulo)->middleware('permiso:'.$modulo.'.gestionar')->name($modulo.'.actualizar');
        Route::delete('/'.$modulo.'/{registro}', [CatalogoController::class, 'archivar'])->defaults('modulo', $modulo)->middleware('permiso:'.$modulo.'.gestionar')->name($modulo.'.archivar');
        Route::post('/'.$modulo.'/{registro}/restaurar', [CatalogoController::class, 'restaurar'])->defaults('modulo', $modulo)->middleware('permiso:'.$modulo.'.gestionar')->name($modulo.'.restaurar');
    }
    Route::middleware('permiso:productos.gestionar')->group(function () {
        Route::get('/productos/crear', [ProductoController::class, 'crear'])->name('productos.crear');
        Route::post('/productos', [ProductoController::class, 'guardar'])->name('productos.guardar');
        Route::get('/productos/{producto}/editar', [ProductoController::class, 'editar'])->name('productos.editar');
        Route::put('/productos/{producto}', [ProductoController::class, 'actualizar'])->name('productos.actualizar');
        Route::delete('/productos/{producto}', [ProductoController::class, 'archivar'])->name('productos.archivar');
        Route::post('/productos/{producto}/restaurar', [ProductoController::class, 'restaurar'])->name('productos.restaurar');
    });
    Route::middleware('permiso:productos.consultar')->group(function () {
        Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
        Route::get('/productos/{producto}', [ProductoController::class, 'mostrar'])->name('productos.mostrar');
        Route::get('/productos/{producto}/imagen', [ProductoController::class, 'imagen'])->name('productos.imagen');
    });
});
