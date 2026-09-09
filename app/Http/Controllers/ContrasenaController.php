<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglaContrasena;

class ContrasenaController extends Controller
{
    public function solicitar()
    {
        return view('autenticacion.recuperar');
    }

    public function enviar(Request $solicitud)
    {
        $solicitud->validate(['email' => 'required|email|max:255']);
        Password::sendResetLink(['email' => mb_strtolower(trim($solicitud->email)), 'estado' => true]);

        return back()->with('estado', 'Si existe una cuenta activa con ese correo, recibirás un enlace de recuperación.');
    }

    public function formulario(Request $solicitud, string $token)
    {
        return view('autenticacion.restablecer', ['token' => $token, 'email' => $solicitud->query('email')]);
    }

    public function restablecer(Request $solicitud)
    {
        $datos = $solicitud->validate(['token' => 'required|string', 'email' => 'required|email|max:255', 'password' => ['required', 'confirmed', 'max:255', ReglaContrasena::min(12)->mixedCase()->numbers()]]);
        $resultado = Password::reset($datos + ['estado' => true], function (Usuario $usuario, string $contrasena) {
            $usuario->forceFill(['password' => $contrasena, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $usuario->id)->delete();
            event(new PasswordReset($usuario));
        });

        return $resultado === Password::PASSWORD_RESET ? redirect()->route('login')->with('estado', 'Contraseña restablecida. Ya puedes ingresar.')
            : back()->withErrors(['email' => 'El enlace es inválido o ha caducado. Solicita uno nuevo.'])->onlyInput('email');
    }
}
