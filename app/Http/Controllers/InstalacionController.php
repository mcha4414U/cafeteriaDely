<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrearAdministradorRequest;
use App\Services\ServicioInstalacion;
use Illuminate\Support\Facades\Auth;

class InstalacionController extends Controller
{
    public function formulario(ServicioInstalacion $servicio)
    {
        if (! $servicio->disponible()) {
            return redirect()->route('login')->with('estado', 'La primera cuenta ya fue creada. Inicia sesión para continuar.');
        }

        return view('autenticacion.crear-administrador');
    }

    public function guardar(CrearAdministradorRequest $solicitud, ServicioInstalacion $servicio)
    {
        $usuario = $servicio->crearAdministrador($solicitud->validated());
        Auth::login($usuario);
        $solicitud->session()->regenerate();

        return redirect()->route('inicio')->with('estado', 'Tu cuenta de administrador está lista. ¡Bienvenido a Cafetería Dely!');
    }
}
