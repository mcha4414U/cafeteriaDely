<?php

namespace App\Http\Controllers;

use App\Http\Requests\IniciarSesionRequest;
use App\Services\ServicioInstalacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AutenticacionController extends Controller
{
    public function formulario()
    {
        return view('autenticacion.ingresar', ['puedeCrearAdministrador' => app(ServicioInstalacion::class)->disponible()]);
    }

    public function ingresar(IniciarSesionRequest $solicitud)
    {
        $clave = 'acceso:'.hash('sha256', $solicitud->email.'|'.$solicitud->ip());
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($clave).' segundos.']);
        }
        if (! Auth::attempt(['email' => $solicitud->email, 'password' => $solicitud->password, 'estado' => true], $solicitud->boolean('recordarme'))) {
            RateLimiter::hit($clave, 60);
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos, o la cuenta está desactivada.']);
        }
        RateLimiter::clear($clave);
        $solicitud->session()->regenerate();

        if ($solicitud->user()->puedeConsultarAvisosStock()) {
            $solicitud->session()->forget('url.intended');
            return redirect()->route('pos.index');
        }

        if ($solicitud->user()->soloPreparaciones()) {
            $solicitud->session()->forget('url.intended');
            return redirect()->route('pedidos.index');
        }

        return redirect()->intended(route('inicio'));
    }

    public function salir(Request $solicitud)
    {
        Auth::logout();
        $solicitud->session()->invalidate();
        $solicitud->session()->regenerateToken();

        return redirect()->route('login')->with('estado', 'Cerraste sesión correctamente.');
    }
}
