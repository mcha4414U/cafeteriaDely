<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_raiz_redirige_al_acceso_interno_y_panel_permanece_privado(): void
    {
        $this->withoutVite();
        $this->get('/')->assertRedirect('/ingresar');
        $this->get('/ingresar')->assertOk()->assertDontSee('Continuar con Google');
        foreach (['/inicio', '/punto-de-venta', '/productos', '/usuarios', '/inventario'] as $ruta) {
            $this->get($ruta)->assertRedirect('/ingresar');
        }
    }

    public function test_rutas_publicas_retiradas_conservan_clientes_historicos(): void
    {
        $cliente = new Cliente(['nombre' => 'Cliente conservado', 'estado' => true]);
        $cliente->google_id = 'identidad-historica';
        $cliente->save();
        foreach (['/auth/google/redirect', '/auth/google/callback', '/menu/imagenes/1'] as $ruta) {
            $this->get($ruta)->assertNotFound();
        }
        $this->post('/cliente/salir')->assertNotFound();
        $this->assertGuest('web');
        $this->assertNull(config('auth.guards.cliente'));
        $this->assertNull(config('auth.providers.clientes'));
        $this->assertNull(config('services.google'));
        $this->assertDatabaseCount('clientes', 1);
        $this->assertSame('identidad-historica', $cliente->fresh()->google_id);
        $this->assertArrayNotHasKey('google_id', $cliente->toArray());
    }
}
