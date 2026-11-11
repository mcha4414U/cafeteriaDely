<?php

namespace Tests\Feature;

use App\Http\Controllers\WhatsAppController;
use App\Models\Usuario;
use App\Services\ServicioWhatsApp;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        Log::spy();
        config(['services.whatsapp' => [
            'enabled' => true,
            'token' => 'credencial-ficticia-solo-para-pruebas',
            'phone_number_id' => '123456789',
            'business_account_id' => '987654321',
            'destinatario' => '59170000000',
            'version' => 'v23.0',
        ]]);
    }

    private function autenticar(bool $gestiona = true): void
    {
        // Usuario simulado; las tablas de la página Productos usan SQLite en memoria.
        $usuario = Mockery::mock(Usuario::class)->makePartial();
        $usuario->forceFill(['id' => 99, 'nombre' => 'Prueba WhatsApp', 'estado' => true]);
        $usuario->setRelation('roles', new Collection);
        $usuario->shouldReceive('tienePermiso')->andReturnUsing(fn ($permiso) => $permiso === 'productos.consultar' || ($gestiona && $permiso === 'productos.gestionar'));
        $this->actingAs($usuario);
    }

    public function test_envia_texto_con_bearer_y_json_correctos(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'identificador-simulado']]], 200)]);
        $resultado = app(ServicioWhatsApp::class)->enviarMensaje('+591 70000000', WhatsAppController::MENSAJE_PRUEBA);
        $this->assertTrue($resultado['exito']);
        Http::assertSent(fn ($solicitud) => $solicitud->method() === 'POST'
            && $solicitud->url() === 'https://graph.facebook.com/v23.0/123456789/messages'
            && $solicitud->hasHeader('Authorization', 'Bearer credencial-ficticia-solo-para-pruebas')
            && $solicitud->data() === ['messaging_product' => 'whatsapp', 'to' => '59170000000', 'type' => 'text', 'text' => ['body' => WhatsAppController::MENSAJE_PRUEBA]]);
        Http::assertSentCount(1);
    }

    public function test_error_de_meta_se_registra_sin_cuerpo_ni_credenciales(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 190, 'error_subcode' => 463, 'message' => 'credencial-ficticia-solo-para-pruebas']], 401)]);
        $resultado = app(ServicioWhatsApp::class)->enviarMensaje('59170000000', 'Prueba');
        $this->assertFalse($resultado['exito']);
        $this->assertStringNotContainsString('credencial-ficticia', $resultado['mensaje']);
        Log::shouldHaveReceived('error')->once()->with('WhatsApp: Meta rechazó el envío o devolvió una respuesta inesperada.', [
            'http_status' => 401, 'codigo_meta' => 190, 'subcodigo_meta' => 463,
        ]);
    }

    public function test_configuracion_o_destinatario_invalido_no_envian_solicitudes(): void
    {
        Http::fake();
        $servicio = app(ServicioWhatsApp::class);
        $this->assertFalse($servicio->enviarMensaje('no-es-un-numero', 'Prueba')['exito']);
        $this->assertFalse($servicio->enviarMensaje('59170000000', '')['exito']);
        config(['services.whatsapp.token' => '']);
        $this->assertFalse($servicio->enviarMensaje('59170000000', 'Prueba')['exito']);
        Http::assertNothingSent();
    }

    public function test_fallo_de_conexion_no_se_reintenta_automaticamente(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $resultado = app(ServicioWhatsApp::class)->enviarMensaje('59170000000', 'Prueba');
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('No se pudo confirmar', $resultado['mensaje']);
        Log::shouldHaveReceived('error')->once()->with('WhatsApp: fallo de conexión o tiempo de espera agotado.', ['categoria' => 'conexion']);
    }

    public function test_respuesta_exitosa_sin_identificador_no_se_presenta_como_envio(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        $this->assertFalse(app(ServicioWhatsApp::class)->enviarMensaje('59170000000', 'Prueba')['exito']);
    }

    public function test_rutas_exigen_autenticacion(): void
    {
        Http::fake();
        $this->get('/integraciones/whatsapp/prueba')->assertNotFound();
        $this->post('/productos/probar-whatsapp')->assertRedirect('/ingresar');
        Http::assertNothingSent();
    }

    public function test_pagina_no_envia_y_post_usa_solo_el_destinatario_configurado(): void
    {
        $this->autenticar();
        Http::fake(['*' => Http::response(['messages' => [['id' => 'identificador-simulado']]], 200)]);
        $this->get('/productos')->assertOk()->assertSee('Enviar aviso de stock bajo')->assertSee('Nuevo producto')->assertSee('name="_token"', false)->assertDontSee('credencial-ficticia');
        Http::assertNothingSent();
        $this->post('/productos/probar-whatsapp', ['destinatario' => '59179999999', 'mensaje' => 'No usar'])
            ->assertRedirect('/productos')->assertSessionHas('estado', 'Meta aceptó el aviso de stock bajo. La entrega al teléfono todavía no está confirmada.');
        Http::assertSent(fn ($solicitud) => $solicitud['to'] === '59170000000' && $solicitud['text']['body'] === WhatsAppController::MENSAJE_PRUEBA);
        $this->post('/productos/probar-whatsapp')->assertStatus(429);
        Http::assertSentCount(1);
    }

    public function test_sin_permiso_no_muestra_boton_ni_permite_enviar(): void
    {
        $this->autenticar(false);
        Http::fake();
        $this->get('/productos')->assertOk()->assertDontSee('Enviar aviso de stock bajo');
        $this->post('/productos/probar-whatsapp')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_token_vencido_se_explica_sin_exponer_credenciales(): void
    {
        $this->autenticar();
        Http::fake(['*' => Http::response(['error' => ['code' => 190, 'error_subcode' => 463]], 401)]);
        $this->post('/productos/probar-whatsapp')->assertRedirect('/productos')
            ->assertSessionHasErrors(['whatsapp' => 'No se pudo enviar el aviso de stock bajo. El token de WhatsApp ha vencido. Actualízalo en la configuración del servidor.']);
    }

    public function test_error_del_envio_se_muestra_mediante_validacion_laravel(): void
    {
        $this->autenticar();
        Http::fake(['*' => Http::response(['error' => ['code' => 131047]], 400)]);
        $this->post('/productos/probar-whatsapp')->assertRedirect('/productos')->assertSessionHasErrors('whatsapp');
    }
}
