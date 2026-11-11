<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DesperdicioController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\RecomendacionController;
use App\Http\Controllers\ResumenController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/integraciones/whatsapp/webhook', [WhatsAppWebhookController::class, 'verificar']);
Route::post('/integraciones/whatsapp/webhook', [WhatsAppWebhookController::class, 'recibir']);
Route::middleware(['auth', 'activo', 'auth.session'])->group(function () {
    Route::get('/notificaciones/stock', [AlertaController::class, 'notificaciones'])->middleware('permiso:pos.usar')->name('notificaciones.stock');
    Route::post('/notificaciones/stock/{alerta}/avisar', [AlertaController::class, 'avisarAdministrador'])->whereNumber('alerta')->middleware(['permiso:pos.usar', 'throttle:avisos-stock'])->name('notificaciones.stock.avisar');
    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->middleware('permiso:configuracion.gestionar')->name('configuracion.index');
    Route::post('/configuracion', [ConfiguracionController::class, 'guardar'])->middleware('permiso:configuracion.gestionar')->name('configuracion.guardar');
    Route::get('/recomendaciones', [RecomendacionController::class, 'index'])->middleware('permiso:recomendaciones.ver')->name('recomendaciones.index');
    Route::get('/reportes', [ResumenController::class, 'reportes'])->middleware('permiso:reportes.ver')->name('reportes.index');
    Route::get('/desperdicios', [DesperdicioController::class, 'index'])->middleware('permiso:desperdicios.gestionar')->name('desperdicios.index');
    Route::post('/desperdicios', [DesperdicioController::class, 'guardar'])->middleware('permiso:desperdicios.gestionar')->name('desperdicios.guardar');
    Route::get('/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
    Route::get('/pedidos/crear', [PedidoController::class, 'crear'])->name('pedidos.crear');
    Route::post('/pedidos', [PedidoController::class, 'guardar'])->name('pedidos.guardar');
    Route::get('/pedidos/{pedido}', [PedidoController::class, 'mostrar'])->name('pedidos.mostrar');
    Route::post('/pedidos/{pedido}/estado', [PedidoController::class, 'estado'])->name('pedidos.estado');
    Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::post('/ventas', [VentaController::class, 'guardar'])->middleware('permiso:pos.usar')->name('ventas.guardar');
    Route::get('/ventas/{venta}', [VentaController::class, 'mostrar'])->name('ventas.mostrar');
    Route::post('/ventas/{venta}/anular', [VentaController::class, 'anular'])->middleware('permiso:ventas.todas')->name('ventas.anular');
    Route::middleware('permiso:alertas.gestionar')->group(function () {
        Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
        Route::post('/alertas/actualizar', [AlertaController::class, 'actualizar'])->name('alertas.actualizar');
        Route::post('/alertas/{alerta}/reintentar', [AlertaController::class, 'reintentar'])->middleware('throttle:5,1')->name('alertas.reintentar');
    });
});
