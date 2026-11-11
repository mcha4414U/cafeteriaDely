<?php

namespace Tests\Feature;

use App\Jobs\EnviarAlertaStock;
use App\Models\{Cliente, Producto, Proveedor, Rol, Usuario};
use App\Services\{ServicioAlertas, ServicioCompras, ServicioInventario, ServicioWhatsApp};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Queue};
use Illuminate\Support\Str;
use Tests\TestCase;

class CajeroNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        Queue::fake();
        Http::preventStrayRequests();
        config(['services.whatsapp.enabled'=>true, 'services.whatsapp.token'=>'token-simulado',
            'services.whatsapp.phone_number_id'=>'12345','services.whatsapp.destinatario'=>'59170000000']);
    }

    private function usuario(string $rol = 'Cajero'): Usuario
    {
        $u = Usuario::create(['nombre'=>$rol, 'email'=>Str::uuid().'@example.test','password'=>'Clave123456','estado'=>true]);
        $u->roles()->attach(Rol::where('nombre',$rol)->firstOrFail());
        return $u;
    }

    private function insumo(array $datos = []): Producto
    {
        return Producto::create($datos + ['nombre'=>'Leche','tipo'=>'insumo','unidad_medida'=>'ml',
            'stock_actual'=>4900,'stock_minimo'=>5000,'estado'=>true,'precio_venta'=>0,'precio_compra'=>0]);
    }

    private function alerta(Producto $p): int
    {
        app(ServicioAlertas::class)->sincronizar($p);
        return (int) DB::table('alertas_stock')->where('producto_id',$p->id)->where('estado','activa')->whereIn('tipo_alerta',['stock_bajo','agotado'])->value('id');
    }

    public function test_cajero_entra_al_pos_incluso_con_destino_previo_y_admin_conserva_inicio(): void
    {
        $c = $this->usuario();
        $this->withSession(['url.intended'=>url('/ventas')])->post('/ingresar',['email'=>$c->email,'password'=>'Clave123456'])->assertRedirect('/punto-de-venta');
        $this->get('/ingresar')->assertRedirect('/punto-de-venta');
        $this->post('/salir');
        $a = $this->usuario('Administrador');
        $this->post('/ingresar',['email'=>$a->email,'password'=>'Clave123456'])->assertRedirect('/inicio');
        $this->get('/ingresar')->assertRedirect('/inicio');
        $this->post('/salir');
        $a->roles()->attach(Rol::where('nombre','Cajero')->value('id'));
        $this->post('/ingresar',['email'=>$a->email,'password'=>'Clave123456'])->assertRedirect('/inicio');
        $this->assertFalse($a->esCajero());
    }

    public function test_menu_cajero_pos_primero_activo_sin_ampliar_permisos(): void
    {
        $c = $this->usuario();
        $html = $this->actingAs($c)->get('/punto-de-venta')->assertOk()->assertSee('data-notificaciones-stock',false)->assertDontSee('ACCESOS ADICIONALES')->getContent();
        $menu = substr($html, strpos($html,'role="menu"'), strpos($html,'</nav>')-strpos($html,'role="menu"'));
        $this->assertStringContainsString('nav-link active', $menu);
        $this->assertTrue(strpos($menu,'Punto de venta') < strpos($menu,'Preparaciones'));
        $this->assertTrue(strpos($menu,'Preparaciones') < strpos($menu,'Inventario'));
        $this->assertTrue(strpos($menu,'Clientes') < strpos($menu,'Historial de ventas'));
        $this->assertTrue(strpos($menu,'Inventario') < strpos($menu,'Clientes'));
        $this->assertSame(1,substr_count($menu,'Punto de venta'));
        $this->assertFalse($c->tienePermiso('alertas.gestionar'));
        $this->assertFalse($c->tienePermiso('inventario.gestionar'));
        $this->flushSession();
        $html = $this->actingAs($this->usuario('Administrador'))->get('/inicio')->assertOk()->assertDontSee('ACCESOS ADICIONALES')->assertDontSee('data-notificaciones-stock',false)->getContent();
        $orden = ['Punto de venta','Preparaciones','Inventario','Compras','Alertas','Clientes','Usuarios y permisos','Reportes','Historial de ventas','Configuración'];
        $anterior = -1;
        foreach ($orden as $nombre) {
            $posicion = strpos($html, '<p>'.$nombre.'</p>');
            $this->assertNotFalse($posicion);
            $this->assertGreaterThan($anterior, $posicion);
            $anterior = $posicion;
        }
        foreach (['Inventario','Compras','Reportes','Alertas','Configuración'] as $nombre) {
            $this->assertStringContainsString($nombre,$html);
        }
    }

    public function test_venta_receta_alerta_unica_compra_resuelve_y_nueva_caida_reavisa(): void
    {
        $c = $this->usuario();
        $a = $this->usuario('Administrador');
        $this->actingAs($c);
        $leche = $this->insumo(['stock_actual'=>5200]);
        $p = Producto::create(['nombre'=>'Capuchino','tipo'=>'venta','estado'=>true,'precio_venta'=>16,'stock_actual'=>0,'stock_minimo'=>0,'unidad_medida'=>'unidad']);
        $r = $p->receta()->create(['nombre'=>'Capuchino','estado'=>true]);
        $r->detalles()->create(['insumo_id'=>$leche->id,'cantidad'=>300,'unidad_medida'=>'ml']);
        $d = ['clave_idempotencia'=>(string) Str::uuid(),'cliente_id'=>Cliente::where('es_generico',true)->value('id'),
            'metodo_pago_id'=>DB::table('metodos_pago')->where('nombre','Efectivo')->value('id'),'descuento'=>0,'recibido'=>20,
            'detalles'=>[['producto_id'=>$p->id,'cantidad'=>1,'precio_esperado'=>16]]];
        $this->postJson('/ventas',$d)->assertOk();
        $id = DB::table('alertas_stock')->where('producto_id',$leche->id)->value('id');
        $this->assertSame('4900.000',$leche->fresh()->stock_actual);
        $this->getJson('/notificaciones/stock')->assertOk()->assertJsonPath('cantidad',1)->assertSee('Leche');
        $this->assertDatabaseMissing('alertas_stock',['producto_id'=>$p->id]);
        Queue::assertPushed(EnviarAlertaStock::class,1);
        $d['clave_idempotencia'] = (string) Str::uuid();
        $this->postJson('/ventas',$d)->assertOk();
        Queue::assertPushed(EnviarAlertaStock::class,1);
        $this->assertDatabaseCount('alertas_stock',1);
        $this->getJson('/notificaciones/stock')->assertJsonPath('cantidad',1)->assertSee('4.600');

        $proveedor = Proveedor::create(['nombre'=>'Proveedor','estado'=>true]);
        app(ServicioCompras::class)->registrar(['clave_idempotencia'=>(string) Str::uuid(),'proveedor_id'=>$proveedor->id,'fecha'=>today()->toDateString(),
            'detalles'=>[['producto_id'=>$leche->id,'cantidad'=>10000,'costo_unitario'=>0]]],$a->id);
        $this->assertDatabaseHas('alertas_stock',['id'=>$id,'estado'=>'resuelta','clave_activa'=>null]);
        $this->getJson('/notificaciones/stock')->assertJsonPath('cantidad',0);
        app(ServicioInventario::class)->ajustar($leche,['stock_esperado'=>14600,'stock_nuevo'=>4900,'motivo'=>'Conteo físico'],$a->id);
        Queue::assertPushed(EnviarAlertaStock::class,2);
        $this->assertDatabaseCount('alertas_stock',2);
        $this->getJson('/notificaciones/stock')->assertJsonPath('cantidad',1);
    }

    public function test_campana_excluye_vencimientos_resueltos_y_datos_privados(): void
    {
        $this->actingAs($this->usuario());
        $p = $this->insumo(['stock_actual'=>0,'fecha_vencimiento'=>today()->addDay()]);
        $id = $this->alerta($p);
        $this->assertDatabaseCount('alertas_stock',2);
        DB::table('alertas_stock')->where('id',$id)->update(['error_envio'=>'privado-error','mensaje'=>'privado-mensaje']);
        $r = $this->getJson('/notificaciones/stock')->assertOk()->assertJsonPath('cantidad',1)->assertSee('Producto agotado');
        foreach (['privado-error','privado-mensaje','token-simulado','59170000000','proximo_vencimiento'] as $secreto) $r->assertDontSee($secreto);
        $vencimiento = DB::table('alertas_stock')->where('tipo_alerta','proximo_vencimiento')->value('id');
        $this->postJson('/notificaciones/stock/'.$vencimiento.'/avisar')->assertNotFound();
        $p->update(['stock_actual'=>10000]);
        app(ServicioAlertas::class)->sincronizar($p);
        $this->getJson('/notificaciones/stock')->assertJsonPath('cantidad',0);
        $this->postJson('/notificaciones/stock/'.$id.'/avisar')->assertNotFound();
    }

    public function test_reaviso_reutiliza_job_y_cooldown_global_entre_cajeros(): void
    {
        Http::fake(['graph.facebook.com/*'=>Http::response(['messages'=>[['id'=>'wamid.prueba']]],200)]);
        $p = $this->insumo();
        $id = $this->alerta($p);
        (new EnviarAlertaStock($id))->handle(app(ServicioWhatsApp::class));
        $this->actingAs($this->usuario())->postJson('/notificaciones/stock/'.$id.'/avisar')->assertStatus(429);
        $this->travel(11)->minutes();
        $this->postJson('/notificaciones/stock/'.$id.'/avisar', ['destinatario'=>'59179999999'])->assertStatus(202)->assertSee('encolado');
        $this->postJson('/notificaciones/stock/'.$id.'/avisar')->assertStatus(429);
        $this->flushSession();
        $this->actingAs($this->usuario())->postJson('/notificaciones/stock/'.$id.'/avisar')->assertStatus(429);
        Queue::assertPushed(EnviarAlertaStock::class,2);
        (new EnviarAlertaStock($id))->handle(app(ServicioWhatsApp::class));
        (new EnviarAlertaStock($id))->handle(app(ServicioWhatsApp::class));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request)=>$request['to']==='59170000000');
        $this->assertDatabaseHas('alertas_stock',['id'=>$id,'envio_estado'=>'aceptado']);
    }

    public function test_whatsapp_deshabilitado_no_finge_envio_y_throttle_limita_solicitudes(): void
    {
        config(['services.whatsapp.enabled'=>false]);
        $id = $this->alerta($this->insumo());
        $this->actingAs($this->usuario());
        for ($i=0;$i<5;$i++) $this->postJson('/notificaciones/stock/'.$id.'/avisar')->assertStatus(503);
        $this->postJson('/notificaciones/stock/'.$id.'/avisar')->assertStatus(429)->assertSee('Espera un minuto');
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->assertDatabaseHas('alertas_stock',['id'=>$id,'ultimo_aviso_manual_at'=>null]);
    }

    public function test_seguridad_backend_sin_permisos_administrativos(): void
    {
        $this->get('/notificaciones/stock')->assertRedirect('/ingresar');
        $c = $this->usuario();
        $this->actingAs($c);
        foreach (['/alertas','/configuracion','/usuarios','/compras','/inventario','/reportes'] as $ruta) $this->get($ruta)->assertForbidden();
        $p = $this->insumo();
        foreach (['/alertas/actualizar','/alertas/1/reintentar','/configuracion','/productos/'.$p->id.'/ajustar-stock'] as $ruta) $this->post($ruta,[])->assertForbidden();
        $c->roles()->detach();
        $this->getJson('/notificaciones/stock')->assertForbidden();
        $this->postJson('/notificaciones/stock/1/avisar')->assertForbidden();
    }

    public function test_desperdicio_dispara_misma_alerta_y_agotado_reemplaza_stock_bajo(): void
    {
        $a = $this->usuario('Administrador');
        $p = $this->insumo(['stock_actual'=>5200]);
        $this->actingAs($a)->post('/desperdicios',['clave'=>(string) Str::uuid(),'producto_id'=>$p->id,'cantidad'=>500,'motivo'=>'desperdiciado','observacion'=>'Derrame durante preparación'])->assertSessionHasNoErrors();
        $id = DB::table('alertas_stock')->where('producto_id',$p->id)->value('id');
        $this->assertDatabaseHas('alertas_stock',['id'=>$id,'tipo_alerta'=>'stock_bajo','estado'=>'activa']);
        app(ServicioInventario::class)->ajustar($p,['stock_esperado'=>4700,'stock_nuevo'=>0,'motivo'=>'Inventario físico'],$a->id);
        $this->assertDatabaseHas('alertas_stock',['id'=>$id,'estado'=>'resuelta']);
        $this->assertDatabaseHas('alertas_stock',['producto_id'=>$p->id,'tipo_alerta'=>'agotado','estado'=>'activa']);
        Queue::assertPushed(EnviarAlertaStock::class,2);
    }
}
