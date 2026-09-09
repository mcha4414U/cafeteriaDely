<?php

use App\Http\Controllers\AutenticacionController;
use App\Http\Controllers\ContrasenaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/catalogos.php';

Route::get('/', fn () => redirect()->route(auth()->check() ? 'inicio' : 'login'));
Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AutenticacionController::class, 'formulario'])->name('login');
    Route::post('/ingresar', [AutenticacionController::class, 'ingresar'])->middleware('throttle:20,1')->name('sesion.ingresar');
    Route::get('/recuperar-contrasena', [ContrasenaController::class, 'solicitar'])->name('password.request');
    Route::post('/recuperar-contrasena', [ContrasenaController::class, 'enviar'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [ContrasenaController::class, 'formulario'])->name('password.reset');
    Route::post('/restablecer-contrasena', [ContrasenaController::class, 'restablecer'])->middleware('throttle:10,1')->name('password.update');
});
Route::middleware(['auth', 'activo', 'auth.session'])->group(function () {
    Route::get('/inicio', fn () => view('inicio'))->name('inicio');
    Route::post('/salir', [AutenticacionController::class, 'salir'])->name('sesion.salir');
    Route::middleware('permiso:usuarios.gestionar')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/crear', [UsuarioController::class, 'crear'])->name('usuarios.crear');
        Route::post('/usuarios', [UsuarioController::class, 'guardar'])->name('usuarios.guardar');
        Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'editar'])->name('usuarios.editar');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'actualizar'])->name('usuarios.actualizar');
    });
});
