<?php

namespace App\Console\Commands;

use App\Services\ServicioInstalacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CrearAdministrador extends Command
{
    protected $signature = 'dely:crear-administrador';

    protected $description = 'Crear el primer administrador mediante datos solicitados en la terminal';

    public function handle(ServicioInstalacion $servicio): int
    {
        if (! $servicio->disponible()) {
            $this->error('Ya existe un administrador activo. Usa la gestión de usuarios.');

            return self::FAILURE;
        }
        $datos = ['nombre' => $this->ask('Nombre'), 'email' => mb_strtolower(trim($this->ask('Correo electrónico') ?? '')),
            'password' => $this->secret('Contraseña (12 caracteres, mayúsculas, minúsculas y números)')];
        $datos['password_confirmation'] = $this->secret('Repite la contraseña');
        $validacion = Validator::make($datos, ['nombre' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:usuarios,email', 'password' => ['required', 'confirmed', 'max:255', Password::min(12)->mixedCase()->numbers()]]);
        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        $servicio->crearAdministrador($validacion->validated());
        $this->info('Administrador creado. Ingresa en /ingresar.');

        return self::SUCCESS;
    }
}
