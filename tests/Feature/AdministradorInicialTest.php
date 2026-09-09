<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministradorInicialTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_administrador_sin_clave_predeterminada(): void
    {
        $this->artisan('dely:crear-administrador')
            ->expectsQuestion('Nombre', 'Administración Dely')
            ->expectsQuestion('Correo electrónico', 'admin@dely.test')
            ->expectsQuestion('Contraseña (12 caracteres, mayúsculas, minúsculas y números)', 'ClaveCafe12345')
            ->expectsQuestion('Repite la contraseña', 'ClaveCafe12345')
            ->expectsOutput('Administrador creado. Ingresa en /ingresar.')
            ->assertSuccessful();
        $usuario = Usuario::where('email', 'admin@dely.test')->firstOrFail();
        $this->assertTrue($usuario->tienePermiso('usuarios.gestionar'));
        $this->artisan('dely:crear-administrador')->expectsOutput('Ya existe un administrador activo. Usa la gestión de usuarios.')->assertFailed();
    }
}
