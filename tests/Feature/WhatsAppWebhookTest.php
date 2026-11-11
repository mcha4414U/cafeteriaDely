<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Services\ServicioAlertas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['services.whatsapp.enabled' => true, 'services.whatsapp.app_secret' => 'secreto-simulado', 'services.whatsapp.webhook_verify_token' => 'verificacion-simulada', 'services.whatsapp.phone_number_id' => '12345']);
    }

    public function test_verificacion_y_firma_invalidas_no_actualizan()
    {
        $this->get('/integraciones/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=incorrecto&hub_challenge=42')->assertForbidden();
        $this->get('/integraciones/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=verificacion-simulada&hub_challenge=42')->assertOk()->assertSee('42');
        $this->postJson('/integraciones/whatsapp/webhook', ['object' => 'whatsapp_business_account'])->assertForbidden();
    }

    public function test_entrega_verificada_no_retrocede_por_eventos_repetidos()
    {
        $p = Producto::create(['nombre' => 'Café', 'tipo' => 'venta', 'estado' => true, 'precio_venta' => 10, 'stock_actual' => 0, 'stock_minimo' => 1]);
        app(ServicioAlertas::class)->sincronizar($p);
        DB::table('alertas_stock')->update(['mensaje_meta_id' => 'wamid.simulado', 'envio_estado' => 'aceptado']);
        foreach ([['delivered', 100], ['sent', 90], ['sent', 110], ['read', 120], ['failed', 130]] as [$estado,$fecha]) {
            $payload = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => ['metadata' => ['phone_number_id' => '12345'], 'statuses' => [['id' => 'wamid.simulado', 'status' => $estado, 'timestamp' => (string) $fecha]]]]]]]], JSON_THROW_ON_ERROR);
            $this->call('POST', '/integraciones/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'secreto-simulado')], $payload)->assertOk();
        }
        $this->assertDatabaseHas('alertas_stock', ['envio_estado' => 'leido', 'meta_evento_at' => 120]);
    }
}
