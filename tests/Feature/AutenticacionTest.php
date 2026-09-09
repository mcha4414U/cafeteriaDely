<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\RestablecerContrasena;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermisosSeeder::class);
    }

    private function usuario(string $rol = 'Administrador', array $datos = []): Usuario
    {
        $usuario = Usuario::create($datos + ['nombre' => 'María', 'email' => uniqid().'@dely.test', 'password' => 'SeguraCafe1234', 'estado' => true]);
        $usuario->roles()->attach(Rol::where('nombre', $rol)->firstOrFail());

        return $usuario;
    }

    public function test_visitante_no_accede_a_usuarios(): void
    {
        $this->get('/usuarios')->assertRedirect('/ingresar');
    }

    public function test_formulario_de_acceso_esta_en_espanol(): void
    {
        $this->get('/ingresar')->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_inicio_de_sesion_y_salida(): void
    {
        $usuario = $this->usuario();
        $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'SeguraCafe1234'])->assertRedirect('/inicio');
        $this->assertAuthenticatedAs($usuario);
        $this->get('/inicio')->assertOk();
        $this->post('/salir')->assertRedirect('/ingresar');
        $this->assertGuest();
    }

    public function test_contrasena_incorrecta_no_autentica(): void
    {
        $usuario = $this->usuario();
        $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_cuenta_inactiva_no_autentica(): void
    {
        $usuario = $this->usuario(datos: ['estado' => false]);
        $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'SeguraCafe1234'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_se_limita_el_numero_de_intentos(): void
    {
        $usuario = $this->usuario();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'incorrecta']);
        }
        $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'SeguraCafe1234'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_cajero_no_accede_ni_modifica_usuarios(): void
    {
        $cajero = $this->usuario('Cajero');
        $this->actingAs($cajero)->get('/usuarios')->assertForbidden();
        $this->post('/usuarios', [])->assertForbidden();
        $this->put('/usuarios/'.$cajero->id, [])->assertForbidden();
        $this->get('/inicio')->assertOk()->assertDontSee('Gestionar usuarios');
        $this->assertTrue($cajero->tienePermiso('ventas.propias'));
        $this->assertFalse($cajero->tienePermiso('ventas.todas'));
        $this->assertFalse($cajero->tienePermiso('configuracion.gestionar'));
    }

    public function test_administrador_crea_usuario_con_contrasena_cifrada(): void
    {
        $administrador = $this->usuario();
        $this->actingAs($administrador)->get('/usuarios')->assertOk();
        $this->get('/usuarios/crear')->assertOk()->assertSee('Un nuevo integrante');
        $this->get('/usuarios/'.$administrador->id.'/editar')->assertOk()->assertSee('Editar usuario');
        $this->post('/usuarios', ['nombre' => 'Pedro', 'email' => 'pedro@dely.test', 'password' => 'SeguraCafe1234', 'password_confirmation' => 'SeguraCafe1234', 'rol_id' => Rol::where('nombre', 'Cajero')->value('id'), 'estado' => 1])->assertRedirect('/usuarios');
        $nuevo = Usuario::where('email', 'pedro@dely.test')->firstOrFail();
        $this->assertTrue(Hash::check('SeguraCafe1234', $nuevo->password));
        $this->assertTrue($nuevo->roles()->where('nombre', 'Cajero')->exists());
    }

    public function test_no_se_desactiva_el_ultimo_administrador(): void
    {
        $administrador = $this->usuario();
        $this->actingAs($administrador)->put('/usuarios/'.$administrador->id, ['nombre' => $administrador->nombre, 'email' => $administrador->email, 'rol_id' => $administrador->roles->first()->id, 'estado' => 0])->assertSessionHasErrors('estado');
        $this->assertTrue($administrador->fresh()->estado);
    }

    public function test_no_se_degrada_el_ultimo_administrador(): void
    {
        $administrador = $this->usuario();
        $this->actingAs($administrador)->put('/usuarios/'.$administrador->id, ['nombre' => $administrador->nombre, 'email' => $administrador->email, 'rol_id' => Rol::where('nombre', 'Cajero')->value('id'), 'estado' => 1])->assertSessionHasErrors('estado');
    }

    public function test_se_puede_desactivar_otro_usuario(): void
    {
        $administrador = $this->usuario();
        $cajero = $this->usuario('Cajero');
        $this->actingAs($administrador)->put('/usuarios/'.$cajero->id, ['nombre' => $cajero->nombre, 'email' => $cajero->email, 'rol_id' => $cajero->roles->first()->id, 'estado' => 0])->assertRedirect('/usuarios');
        $this->assertFalse($cajero->fresh()->estado);
    }

    public function test_sesion_de_cuenta_desactivada_se_rechaza(): void
    {
        $usuario = $this->usuario(datos: ['estado' => false]);
        $this->actingAs($usuario)->get('/inicio')->assertRedirect('/ingresar');
        $this->assertGuest();
    }

    public function test_validaciones_de_usuario(): void
    {
        $administrador = $this->usuario();
        $this->actingAs($administrador)->post('/usuarios', ['nombre' => 'Pedro', 'email' => $administrador->email, 'password' => '123', 'password_confirmation' => '123', 'rol_id' => 999, 'estado' => 1])->assertSessionHasErrors(['email', 'password', 'rol_id']);
    }

    public function test_recuperacion_y_token_de_un_solo_uso(): void
    {
        Notification::fake();
        $usuario = $this->usuario();
        $this->post('/recuperar-contrasena', ['email' => $usuario->email])->assertSessionHas('estado');
        Notification::assertSentTo($usuario, RestablecerContrasena::class);
        $token = Password::createToken($usuario);
        $datos = ['email' => $usuario->email, 'token' => $token, 'password' => 'NuevaCafe123456', 'password_confirmation' => 'NuevaCafe123456'];
        $this->post('/restablecer-contrasena', $datos)->assertRedirect('/ingresar');
        $this->assertTrue(Hash::check('NuevaCafe123456', $usuario->fresh()->password));
        $this->post('/restablecer-contrasena', $datos)->assertSessionHasErrors('email');
    }

    public function test_token_invalido_no_cambia_contrasena(): void
    {
        $usuario = $this->usuario();
        $this->post('/restablecer-contrasena', ['email' => $usuario->email, 'token' => 'invalido', 'password' => 'NuevaCafe123456', 'password_confirmation' => 'NuevaCafe123456'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('SeguraCafe1234', $usuario->fresh()->password));
    }

    public function test_correo_desconocido_recibe_respuesta_generica(): void
    {
        Notification::fake();
        $this->post('/recuperar-contrasena', ['email' => 'nadie@dely.test'])->assertSessionHas('estado');
        Notification::assertNothingSent();
    }

    public function test_no_hay_registro_publico(): void
    {
        $this->post('/register',[])->assertNotFound();
    }
}
