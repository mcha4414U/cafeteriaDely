<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificarUsuarioActivo
{
    public function handle(Request $solicitud, Closure $siguiente)
    {
        if (! $solicitud->user()?->estado) {
            Auth::logout();
            $solicitud->session()->invalidate();
            $solicitud->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta está desactivada. Contacta al administrador.']);
        }

        return $siguiente($solicitud);
    }
}
