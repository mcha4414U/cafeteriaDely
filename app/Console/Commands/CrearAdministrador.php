<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CrearAdministrador extends Command
{
    protected $signature = 'dely:crear-administrador';

    protected $description = 'Crear el primer administrador mediante datos solicitados en la terminal';

    public function handle(): int
    {
        if (Usuario::whereHas('roles', fn ($consulta) => $consulta->where('nombre', 'Administrador'))->where('estado', true)->exists()) {
            $this->error('Ya existe un administrador activo. Usa la gestión de usuarios.');

            return self::FAILURE;
        }
        $datos = ['nombre' => $this->ask('Nombre'), 'email' => mb_strtolower(trim($this->ask('Correo electrónico') ?? '')),
            'password' => $this->secret('Contraseña (12 caracteres, mayúsculas, minúsculas y números)')];
        $datos['password_confirmation'] = $this->secret('Repite la contraseña');
        $validacion = Validator::make($datos, ['nombre' => 'required|string|max:120', 'email' => 'required|email|unique:usuarios,email', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]]);
        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        (new RolesPermisosSeeder)->run();
        DB::transaction(function () use ($datos) {
            $rol = Rol::where('nombre', 'Administrador')->lockForUpdate()->firstOrFail();
            abort_if($rol->usuarios()->where('estado', true)->exists(), 409);
            $usuario = Usuario::create($datos + ['estado' => true]);
            $usuario->roles()->attach($rol);
        });
        $this->info('Administrador creado. Ingresa en /ingresar.');

        return self::SUCCESS;
    }
}
