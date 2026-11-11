<?php

namespace App\Services;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioInstalacion
{
    public function disponible(): bool
    {
        return DB::table('configuraciones')->where('clave', 'instalacion_completada')->value('valor') === '0' && ! Usuario::exists();
    }

    public function crearAdministrador(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos) {
            // La fila de instalación serializa tanto el registro web como el de consola.
            $configuracion = DB::table('configuraciones')->where('clave', 'instalacion_completada')->lockForUpdate()->first();
            if (! $configuracion || $configuracion->valor !== '0' || Usuario::exists()) {
                throw ValidationException::withMessages(['email' => 'La primera cuenta ya fue creada. Inicia sesión o recupera tu contraseña.']);
            }
            (new RolesPermisosSeeder)->run();
            $usuario = Usuario::create(['nombre' => $datos['nombre'], 'email' => mb_strtolower(trim($datos['email'])), 'password' => $datos['password'], 'estado' => true]);
            $usuario->roles()->attach(Rol::where('nombre', 'Administrador')->firstOrFail());
            DB::table('configuraciones')->where('id', $configuracion->id)->update(['valor' => '1', 'updated_at' => now()]);

            return $usuario;
        }, 3);
    }
}
