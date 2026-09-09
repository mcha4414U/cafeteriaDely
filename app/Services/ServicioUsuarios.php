<?php

namespace App\Services;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicioUsuarios
{
    public function guardar(array $datos, ?Usuario $usuario = null): Usuario
    {
        return DB::transaction(function () use ($datos, $usuario) {
            // Serializar cambios de administradores para conservar al menos uno activo.
            $administrador = Rol::where('nombre', 'Administrador')->lockForUpdate()->firstOrFail();
            if ($usuario) {
                $usuario = Usuario::lockForUpdate()->findOrFail($usuario->id);
                $pierdeAcceso = ! $datos['estado'] || (int) $datos['rol_id'] !== $administrador->id;
                if ($pierdeAcceso && $usuario->estado && $usuario->roles()->whereKey($administrador->id)->exists()
                    && $administrador->usuarios()->where('estado', true)->count() <= 1) {
                    throw ValidationException::withMessages(['estado' => 'Debe permanecer al menos un administrador activo.']);
                }
            } else {
                $usuario = new Usuario;
            }
            $usuario->nombre = $datos['nombre'];
            $usuario->email = $datos['email'];
            $usuario->estado = $datos['estado'];
            if (! empty($datos['password'])) {
                $usuario->password = $datos['password'];
                $usuario->remember_token = Str::random(60);
            }
            if (! $usuario->estado) {
                $usuario->remember_token = Str::random(60);
            }
            $usuario->save();
            $usuario->roles()->sync([$datos['rol_id']]);
            if (! $usuario->estado || ! empty($datos['password'])) {
                DB::table('sessions')->where('user_id', $usuario->id)->delete();
            }

            return $usuario;
        });
    }
}
