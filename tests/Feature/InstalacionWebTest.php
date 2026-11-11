<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstalacionWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function datos(): array
    {
        return ['nombre' => 'Administración Dely', 'email' => 'nuevo@dely.test', 'password' => 'MiCafeSeguro123', 'password_confirmation' => 'MiCafeSeguro123'];
    }

    public function test_muestra_opcion_y_crea_administrador_sin_cuenta_previa(): void
    {
        $this->get('/ingresar')->assertOk()->assertSee('Crear administrador');
        $this->get('/crear-administrador')->assertOk();
        $this->post('/crear-administrador', $this->datos())->assertRedirect('/inicio');
        $usuario = Usuario::firstOrFail();
        $this->assertAuthenticatedAs($usuario);
        $this->assertTrue($usuario->tienePermiso('usuarios.gestionar'));
        $this->assertTrue(Hash::check('MiCafeSeguro123', $usuario->password));
        $this->assertDatabaseHas('configuraciones', ['clave' => 'instalacion_completada', 'valor' => '1']);
        $this->post('/salir');
        $this->get('/ingresar')->assertOk()->assertDontSee('Crear administrador');
        $this->get('/crear-administrador')->assertRedirect('/ingresar');
        $this->post('/crear-administrador', $this->datos())->assertForbidden();
        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_no_reabre_instalacion_con_cuenta_desactivada(): void
    {
        Usuario::create(['nombre' => 'Anterior', 'email' => 'anterior@dely.test', 'password' => 'MiCafeSeguro123', 'estado' => false]);
        $this->get('/crear-administrador')->assertRedirect('/ingresar');
        $this->post('/crear-administrador', $this->datos())->assertForbidden();
    }

    public function test_no_reabre_instalacion_cerrada_aunque_no_haya_usuarios(): void
    {
        DB::table('configuraciones')->where('clave', 'instalacion_completada')->update(['valor' => '1']);
        $this->post('/crear-administrador', $this->datos())->assertForbidden();
    }

    public function test_valida_correo_y_contrasena_y_no_crea_datos_parciales(): void
    {
        $this->post('/crear-administrador', ['nombre' => 'Ana', 'email' => 'invalido', 'password' => '123', 'password_confirmation' => '456'])->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('usuarios', 0);
        $this->assertDatabaseHas('configuraciones', ['clave' => 'instalacion_completada', 'valor' => '0']);
    }
}
