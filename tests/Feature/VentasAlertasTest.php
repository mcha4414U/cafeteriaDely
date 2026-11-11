<?php

namespace Tests\Feature;

use App\Jobs\EnviarAlertaStock;
use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Venta;
use App\Services\ServicioAlertas;
use App\Services\ServicioWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class VentasAlertasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        Queue::fake();
        config(['services.whatsapp.enabled' => true]);
        Http::preventStrayRequests();
        $u = Usuario::create(['nombre' => 'Caja prueba', 'email' => 'caja@example.test', 'password' => 'Clave123456', 'estado' => true]);
        $u->roles()->attach(Rol::where('nombre', 'Administrador')->firstOrFail());
        $this->actingAs($u);
    }

    public function test_alertas_internas_funcionan_con_whatsapp_desactivado(): void
    {
        config(['services.whatsapp.enabled' => false]);
        Http::fake();
        $p = $this->producto(['stock_actual' => '1.000']);
        app(ServicioAlertas::class)->sincronizar($p);
        $this->assertDatabaseHas('alertas_stock', ['producto_id' => $p->id, 'estado' => 'activa']);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->get('/alertas?estado=')->assertOk();
        $this->get('/reportes?desde=&hasta=')->assertOk();
        $this->get('/ventas?hasta='.today()->toDateString())->assertOk();
    }

    private function producto(array $d = []): Producto
    {
        return Producto::create($d + ['nombre' => 'Café', 'tipo' => 'venta', 'estado' => true, 'precio_venta' => '16.00', 'precio_compra' => '5.00', 'stock_actual' => '10.000', 'stock_minimo' => '2.000', 'unidad_medida' => 'unidad']);
    }

    private function datos(Producto $p, array $d = []): array
    {
        return $d + ['clave_idempotencia' => (string) Str::uuid(), 'cliente_id' => Cliente::where('es_generico', true)->value('id'), 'metodo_pago_id' => DB::table('metodos_pago')->where('nombre', 'Efectivo')->value('id'), 'descuento' => '2.00', 'recibido' => '40.00', 'detalles' => [['producto_id' => $p->id, 'cantidad' => 2, 'precio_esperado' => $p->precio_venta]]];
    }

    public function test_cobro_real_calcula_servidor_y_reintento_no_duplica()
    {
        $p = $this->producto();
        $d = $this->datos($p) + ['total' => '0.01'];
        $url = $this->postJson('/ventas', $d)->assertOk()->json('url');
        $this->postJson('/ventas', $d)->assertOk()->assertJson(['url' => $url]);
        $v = Venta::firstOrFail();
        $this->assertSame('30.00', $v->total);
        $this->assertSame('10.00', $v->cambio);
        $this->assertSame('8.000', $p->fresh()->stock_actual);
        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->get($url)->assertOk()->assertSee('30,00')->assertSee('Café');
        $d['detalles'][0]['cantidad'] = 3;
        $this->postJson('/ventas', $d)->assertUnprocessable();
    }

    public function test_stock_insuficiente_precio_cambiado_y_pago_incompleto_no_escriben()
    {
        $p = $this->producto(['stock_actual' => '1']);
        $this->postJson('/ventas', $this->datos($p))->assertUnprocessable();
        $p->update(['stock_actual' => '10']);
        $d = $this->datos($p);
        $d['detalles'][0]['precio_esperado'] = '1.00';
        $this->postJson('/ventas', $d)->assertUnprocessable();
        $this->postJson('/ventas', $this->datos($p, ['recibido' => '0']))->assertUnprocessable();
        $this->postJson('/ventas', $this->datos($p, ['descuento' => '100']))->assertUnprocessable();
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame('10.000', $p->fresh()->stock_actual);
    }

    public function test_recetas_consumen_insumos_compartidos_y_anulacion_restituye_original()
    {
        $insumo = $this->producto(['tipo' => 'insumo', 'nombre' => 'Leche', 'stock_actual' => '1.000', 'stock_minimo' => '0.5', 'unidad_medida' => 'litro']);
        $p = $this->producto(['stock_actual' => 0]);
        $r = $p->receta()->create(['nombre' => 'Preparación', 'estado' => true]);
        $r->detalles()->create(['insumo_id' => $insumo->id, 'cantidad' => '0.250', 'unidad_medida' => 'litro']);
        $this->postJson('/ventas', $this->datos($p))->assertOk();
        $v = Venta::firstOrFail();
        $this->assertSame('0.500', $insumo->fresh()->stock_actual);
        $this->assertSame('0.000', $p->fresh()->stock_actual);
        $this->assertDatabaseHas('alertas_stock', ['producto_id' => $insumo->id, 'estado' => 'activa', 'tipo_alerta' => 'stock_bajo']);
        Queue::assertPushed(EnviarAlertaStock::class, 1);
        $r->detalles()->update(['cantidad' => '0.900']);
        $this->post('/ventas/'.$v->id.'/anular', ['motivo' => 'Error de caja'])->assertRedirect();
        $this->post('/ventas/'.$v->id.'/anular', ['motivo' => 'Error de caja'])->assertRedirect();
        $this->assertSame('1.000', $insumo->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertDatabaseHas('alertas_stock', ['producto_id' => $insumo->id, 'estado' => 'resuelta']);
    }

    public function test_comparte_insumo_entre_lineas_y_rechaza_consumo_total()
    {
        $i = $this->producto(['tipo' => 'insumo', 'stock_actual' => '1', 'unidad_medida' => 'litro']);
        $a = $this->producto();
        $b = $this->producto();
        foreach ([$a, $b] as $p) {
            $r = $p->receta()->create(['nombre' => 'Receta', 'estado' => true]);
            $r->detalles()->create(['insumo_id' => $i->id, 'cantidad' => '0.400', 'unidad_medida' => 'litro']);
        }
        $d = $this->datos($a, ['recibido' => '100']);
        $d['detalles'][] = ['producto_id' => $b->id, 'cantidad' => 1, 'precio_esperado' => '16.00'];
        $this->postJson('/ventas', $d)->assertUnprocessable();
        $this->assertSame('1.000', $i->fresh()->stock_actual);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_cajero_solo_ve_sus_ventas_y_no_anula()
    {
        $p = $this->producto();
        $this->postJson('/ventas', $this->datos($p))->assertOk();
        $v = Venta::firstOrFail();
        $u = Usuario::create(['nombre' => 'Otra caja', 'email' => 'otra@example.test', 'password' => 'Clave123456', 'estado' => true]);
        $u->roles()->attach(Rol::where('nombre', 'Cajero')->firstOrFail());
        $this->flushSession();
        $this->actingAs($u);
        $this->get('/ventas')->assertOk()->assertDontSee($v->numero);
        $this->get('/ventas/'.$v->id)->assertForbidden();
        $this->post('/ventas/'.$v->id.'/anular', ['motivo' => 'Intento indebido'])->assertForbidden();
        foreach (['/alertas', '/reportes', '/configuracion', '/desperdicios', '/recomendaciones'] as $ruta) {
            $this->get($ruta)->assertForbidden();
        }
    }

    public function test_alerta_no_duplica_se_resuelve_y_reabre()
    {
        $p = $this->producto(['stock_actual' => '1']);
        $s = app(ServicioAlertas::class);
        $s->sincronizar($p);
        $s->sincronizar($p);
        $this->assertDatabaseCount('alertas_stock', 1);
        Queue::assertPushed(EnviarAlertaStock::class, 1);
        $p->update(['stock_actual' => '10']);
        $s->sincronizar($p);
        $this->assertDatabaseHas('alertas_stock', ['estado' => 'resuelta', 'clave_activa' => null]);
        $p->update(['stock_actual' => '1']);
        $s->sincronizar($p);
        $this->assertDatabaseCount('alertas_stock', 2);
        Queue::assertPushed(EnviarAlertaStock::class, 2);
    }

    public function test_job_envia_un_aviso_real_sanitizado_y_no_duplica()
    {
        config(['services.whatsapp.token' => 'ficticio', 'services.whatsapp.phone_number_id' => '123456', 'services.whatsapp.destinatario' => '59170000000']);
        $p = $this->producto(['stock_actual' => '1']);
        app(ServicioAlertas::class)->sincronizar($p);
        $id = DB::table('alertas_stock')->value('id');
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.prueba']]], 200)]);
        $j = new EnviarAlertaStock($id);
        $j->handle(app(ServicioWhatsApp::class));
        $j->handle(app(ServicioWhatsApp::class));
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r['text']['body'], 'Café: 1 unidad'));
        $this->assertDatabaseHas('alertas_stock', ['id' => $id, 'envio_estado' => 'aceptado', 'mensaje_meta_id' => 'wamid.prueba']);
    }

    public function test_error_api_no_elimina_alerta_ni_reintenta_solo()
    {
        config(['services.whatsapp.token' => 'ficticio', 'services.whatsapp.phone_number_id' => '123456', 'services.whatsapp.destinatario' => '59170000000']);
        $p = $this->producto(['stock_actual' => '1']);
        app(ServicioAlertas::class)->sincronizar($p);
        $id = DB::table('alertas_stock')->value('id');
        Http::fake(['*' => Http::response(['error' => ['code' => 190, 'error_subcode' => 463, 'message' => 'SECRETO']], 401)]);
        $j = new EnviarAlertaStock($id);
        $j->handle(app(ServicioWhatsApp::class));
        $j->handle(app(ServicioWhatsApp::class));
        Http::assertSentCount(1);
        $this->assertDatabaseHas('alertas_stock', ['id' => $id, 'envio_estado' => 'error', 'enviada' => false]);
        $this->get('/alertas')->assertOk()->assertDontSee('SECRETO');
    }

    public function test_alerta_resuelta_no_envia()
    {
        $p = $this->producto(['stock_actual' => '1']);
        $s = app(ServicioAlertas::class);
        $s->sincronizar($p);
        $id = DB::table('alertas_stock')->value('id');
        $p->update(['stock_actual' => '10']);
        $s->sincronizar($p);
        (new EnviarAlertaStock($id))->handle(app(ServicioWhatsApp::class));
        Http::assertNothingSent();
    }

    public function test_pedido_no_mueve_stock_hasta_cobro_y_no_se_cobra_dos_veces()
    {
        $p = $this->producto();
        // Pedido histórico creado antes de convertir el módulo en Preparaciones.
        $id = DB::table('pedidos')->insertGetId(['numero'=>'PED-ANTERIOR','cliente_id'=>Cliente::first()->id,'usuario_id'=>auth()->id(),'fecha'=>now(),'estado'=>'pendiente','subtotal'=>32,'descuento'=>0,'total'=>32,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('detalle_pedidos')->insert(['pedido_id'=>$id,'producto_id'=>$p->id,'nombre_producto'=>$p->nombre,'cantidad'=>2,'precio_unitario'=>16,'subtotal'=>32,'created_at'=>now(),'updated_at'=>now()]);
        $this->get('/pedidos?anteriores=1')->assertOk()->assertSee('PED-ANTERIOR');
        $this->assertDatabaseCount('pedidos', 1);
        $id = DB::table('pedidos')->value('id');
        $this->assertSame('10.000', $p->fresh()->stock_actual);
        $this->get('/pedidos/'.$id)->assertOk();
        $this->get('/punto-de-venta?pedido='.$id)->assertOk();
        $this->post('/pedidos/'.$id.'/estado', ['estado' => 'en_preparacion'])->assertRedirect();
        $this->postJson('/ventas', $this->datos($p, ['pedido_id' => $id]))->assertOk();
        $this->postJson('/ventas', $this->datos($p, ['pedido_id' => $id]))->assertUnprocessable();
        $this->assertDatabaseHas('pedidos', ['id' => $id, 'estado' => 'completado']);
        $this->assertSame('8.000', $p->fresh()->stock_actual);
    }

    public function test_desperdicio_auditado_idempotente_y_stock_suficiente()
    {
        $p = $this->producto();
        $d = ['clave' => (string) Str::uuid(), 'producto_id' => $p->id, 'cantidad' => '2.5', 'motivo' => 'danado', 'observacion' => 'Envase dañado'];
        $this->post('/desperdicios', $d)->assertRedirect();
        $this->post('/desperdicios', $d)->assertRedirect();
        $this->assertSame('7.500', $p->fresh()->stock_actual);
        $this->assertDatabaseCount('desperdicios', 1);
        $this->assertDatabaseHas('movimientos_inventario', ['tipo' => 'salida_desperdicio', 'cantidad' => '2.500']);
        $d['clave'] = (string) Str::uuid();
        $d['cantidad'] = '100';
        $this->post('/desperdicios', $d)->assertSessionHasErrors('cantidad');
        $this->assertDatabaseCount('desperdicios', 1);
    }

    public function test_pantallas_y_reportes_excluyen_anuladas()
    {
        $p = $this->producto();
        $this->postJson('/ventas', $this->datos($p))->assertOk();
        foreach (['/inicio', '/ventas', '/pedidos', '/alertas', '/reportes', '/desperdicios', '/recomendaciones', '/configuracion'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/pedidos/crear')->assertRedirect('/punto-de-venta');
        $this->get('/reportes')->assertViewHas('total', fn ($v) => (float) $v === 30.0);
        $this->post('/ventas/'.Venta::first()->id.'/anular', ['motivo' => 'Prueba de cierre'])->assertRedirect();
        $this->get('/reportes')->assertViewHas('total', fn ($v) => (float) $v === 0.0);
    }

    public function test_fallo_movimiento_revierte_toda_la_venta()
    {
        $p = $this->producto();
        MovimientoInventario::creating(fn () => throw new \RuntimeException('Fallo simulado'));
        try {
            $this->postJson('/ventas', $this->datos($p))->assertStatus(500);
        } finally {
            MovimientoInventario::flushEventListeners();
        }
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('detalle_ventas', 0);
        $this->assertSame('10.000', $p->fresh()->stock_actual);
    }

    public function test_validacion_rechaza_duplicados_fracciones_y_productos_inactivos()
    {
        $p = $this->producto();
        $d = $this->datos($p);
        $d['detalles'][] = $d['detalles'][0];
        $this->postJson('/ventas', $d)->assertUnprocessable();
        $d = $this->datos($p);
        $d['detalles'][0]['cantidad'] = '0.5';
        $this->postJson('/ventas',$d)->assertUnprocessable();
        $p->update(['estado' => false]);
        $this->postJson('/ventas',$this->datos($p))->assertUnprocessable();
        $this->assertDatabaseCount('ventas',0);
    }
}
