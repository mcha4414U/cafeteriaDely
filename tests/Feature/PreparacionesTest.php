<?php

namespace Tests\Feature;

use App\Models\{Cliente, Producto, Rol, Usuario, Venta};
use App\Services\ServicioPreparaciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Queue};
use Illuminate\Support\Str;
use Tests\TestCase;

class PreparacionesTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $cajero;
    private Producto $leche;
    private Producto $capuchino;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        Queue::fake();
        Http::preventStrayRequests();
        $this->cajero = $this->usuario('Cajero');
        $this->actingAs($this->cajero);
        $this->leche = Producto::create(['nombre'=>'Leche','tipo'=>'insumo','unidad_medida'=>'ml','stock_actual'=>2000,'stock_minimo'=>100,'precio_venta'=>0,'estado'=>true]);
        $this->capuchino = Producto::create(['nombre'=>'Capuchino','tipo'=>'venta','unidad_medida'=>'unidad','stock_actual'=>0,'stock_minimo'=>0,'precio_venta'=>15,'estado'=>true]);
        $receta = $this->capuchino->receta()->create(['nombre'=>'Receta de capuchino','descripcion'=>'Calentar y servir','estado'=>true]);
        $receta->detalles()->create(['insumo_id'=>$this->leche->id,'cantidad'=>150,'unidad_medida'=>'ml']);
    }

    private function usuario(string $rol): Usuario
    {
        $u = Usuario::create(['nombre'=>$rol,'email'=>Str::uuid().'@example.test','password'=>'Clave123456','estado'=>true]);
        $u->roles()->attach(Rol::where('nombre',$rol)->firstOrFail());
        return $u;
    }

    private function datos(bool $personalizada = true): array
    {
        $linea = ['producto_id'=>$this->capuchino->id,'cantidad'=>2,'precio_esperado'=>15];
        if ($personalizada) {
            $receta = app(ServicioPreparaciones::class)->receta($this->capuchino->receta()->with('detalles.insumo')->firstOrFail());
            $linea['indicaciones'] = [['unidad'=>1,'version'=>$receta['version'],'nota'=>'Más leche',
                'insumos'=>[['insumo_id'=>$this->leche->id,'cantidad'=>'170']]]];
        }
        return ['clave_idempotencia'=>(string) Str::uuid(),'cliente_id'=>Cliente::where('es_generico',true)->value('id'),
            'metodo_pago_id'=>DB::table('metodos_pago')->where('nombre','Efectivo')->value('id'),
            'descuento'=>0,'recibido'=>40,'detalles'=>[$linea]];
    }

    public function test_cajero_entra_al_pos_y_puede_abrir_preparaciones(): void
    {
        auth()->logout();
        $this->flushSession();
        $cajero = $this->usuario('Cajero');
        $this->withSession(['url.intended'=>url('/usuarios')])->post('/ingresar',['email'=>$cajero->email,'password'=>'Clave123456'])->assertRedirect('/punto-de-venta');
        $this->get('/pedidos')->assertOk();
    }

    public function test_fallo_al_guardar_preparacion_revierte_venta_stock_y_movimientos(): void
    {
        $servicio = \Mockery::mock(ServicioPreparaciones::class)->makePartial();
        $servicio->shouldReceive('registrar')->once()->andThrow(new \RuntimeException('Fallo simulado'));
        $this->app->instance(ServicioPreparaciones::class,$servicio);
        $this->postJson('/ventas',$this->datos(false))->assertStatus(500);
        $this->assertDatabaseCount('ventas',0);
        $this->assertDatabaseCount('detalle_ventas',0);
        $this->assertDatabaseCount('pedidos',0);
        $this->assertDatabaseCount('movimientos_inventario',0);
        $this->assertSame('2000.000',$this->leche->fresh()->stock_actual);
    }

    public function test_cobro_guarda_dos_unidades_independientes_y_reintento_no_duplica(): void
    {
        $datos = $this->datos();
        $url = $this->postJson('/ventas',$datos)->assertOk()->json('url');
        $this->postJson('/ventas',$datos)->assertOk()->assertJsonPath('url',$url);
        $filas = DB::table('detalle_pedidos')->orderBy('unidad_numero')->get();
        $this->assertCount(2,$filas);
        $primera = json_decode($filas[0]->preparacion,true);
        $segunda = json_decode($filas[1]->preparacion,true);
        $this->assertSame('170.000',$primera['insumos'][0]['cantidad']);
        $this->assertTrue($primera['personalizado']);
        $this->assertSame('150.000',$segunda['insumos'][0]['cantidad']);
        $this->assertFalse($segunda['personalizado']);
        $this->assertDatabaseCount('ventas',1);
        $this->assertDatabaseCount('pedidos',1);
        $this->assertDatabaseCount('movimientos_inventario',1);
        $this->assertSame('1700.000',$this->leche->fresh()->stock_actual);
        $this->assertDatabaseHas('detalle_recetas',['cantidad'=>150]);
        $datos['detalles'][0]['indicaciones'][0]['insumos'][0]['cantidad'] = 180;
        $this->postJson('/ventas',$datos)->assertUnprocessable();
        $this->get('/ventas')->assertOk()->assertSee('Historial de ventas')->assertSee(Venta::first()->numero);
    }

    public function test_venta_posterior_original_y_snapshot_resiste_edicion_maestra(): void
    {
        $this->postJson('/ventas',$this->datos())->assertOk();
        $this->postJson('/ventas',$this->datos(false))->assertOk();
        $ultima = json_decode(DB::table('detalle_pedidos')->orderByDesc('id')->value('preparacion'),true);
        $this->assertSame('150.000',$ultima['insumos'][0]['cantidad']);
        $this->assertFalse($ultima['personalizado']);
        $this->capuchino->receta->detalles()->update(['cantidad'=>200]);
        $this->flushSession();
        $this->actingAs($this->usuario('Administrador'))->get('/pedidos')->assertOk()
            ->assertSee('170 ml')->assertSee('150 ml')->assertSee('Más leche')->assertSee('PERSONALIZADO')->assertDontSee('200 ml');
    }

    public function test_estado_secuencial_y_reintento_no_cobran_ni_descontan(): void
    {
        $this->postJson('/ventas',$this->datos())->assertOk();
        $filas = DB::table('detalle_pedidos')->get();
        $this->flushSession();
        $this->actingAs($this->cajero);
        foreach ($filas as $f) {
            $ruta = '/pedidos/'.$f->pedido_id.'/estado';
            $this->postJson($ruta,['detalle_id'=>$f->id,'estado'=>'listo'])->assertUnprocessable();
            $this->post($ruta,['detalle_id'=>$f->id,'estado'=>'en_preparacion'])->assertSessionHasNoErrors();
            $this->post($ruta,['detalle_id'=>$f->id,'estado'=>'en_preparacion'])->assertSessionHasNoErrors();
            $this->post($ruta,['detalle_id'=>$f->id,'estado'=>'listo'])->assertSessionHasNoErrors();
            $this->postJson($ruta,['detalle_id'=>$f->id,'estado'=>'en_preparacion'])->assertUnprocessable();
        }
        $this->assertDatabaseHas('pedidos',['id'=>$filas[0]->pedido_id,'estado'=>'completado']);
        $this->get('/pedidos')->assertDontSee('Calentar y servir');
        $this->get('/pedidos?estado=listo')->assertSee('Calentar y servir')->assertDontSee('Marcar como listo');
        $this->assertDatabaseCount('ventas',1);
        $this->assertDatabaseCount('movimientos_inventario',1);
        $this->assertSame('1700.000',$this->leche->fresh()->stock_actual);
    }

    public function test_administrador_todas_las_preparaciones_y_cajero_solo_las_propias(): void
    {
        $this->postJson('/ventas',$this->datos())->assertOk();
        $f = DB::table('detalle_pedidos')->first();
        $this->flushSession();
        $this->actingAs($this->usuario('Cajero'))->get('/pedidos')->assertOk()->assertDontSee('Calentar y servir');
        $this->get('/pedidos/'.$f->pedido_id)->assertForbidden();
        $this->post('/pedidos/'.$f->pedido_id.'/estado',['detalle_id'=>$f->id,'estado'=>'en_preparacion'])->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->usuario('Administrador'))->get('/pedidos')->assertOk()->assertSee('Calentar y servir');
        $this->post('/pedidos/'.$f->pedido_id.'/estado',['detalle_id'=>$f->id,'estado'=>'en_preparacion'])->assertSessionHasNoErrors();
        $this->get('/pedidos/'.$f->pedido_id)->assertOk();
    }

    public function test_no_receta_no_preparacion_y_no_creacion_manual(): void
    {
        $this->capuchino->receta->detalles()->delete();
        $this->capuchino->receta()->delete();
        $this->capuchino->update(['stock_actual'=>10]);
        $this->postJson('/ventas',$this->datos(false))->assertOk();
        $this->assertDatabaseCount('pedidos',0);
        $this->assertDatabaseCount('detalle_pedidos',0);
        $this->get('/pedidos/crear')->assertRedirect('/punto-de-venta');
        $this->postJson('/pedidos',[])->assertUnprocessable();
        $this->assertDatabaseCount('ventas',1);
    }

    public function test_pos_entrega_receta_original_y_modal_sin_editar_maestra(): void
    {
        $sin = Producto::create(['nombre'=>'Agua','tipo'=>'venta','unidad_medida'=>'unidad','stock_actual'=>5,'stock_minimo'=>0,'precio_venta'=>5,'estado'=>true]);
        $this->get('/punto-de-venta')->assertOk()->assertSee('id="modal-receta"',false)->assertSee('Restablecer')
            ->assertViewHas('datosProductos',function($productos) use ($sin) {
                $this->assertNull($productos->firstWhere('id',$sin->id)['receta']);
                $receta = $productos->firstWhere('id',$this->capuchino->id)['receta'];
                $this->assertSame('150.000',$receta['insumos'][0]['cantidad']);
                return true;
            });
        $this->assertDatabaseCount('ventas',0);
        $this->assertSame('2000.000',$this->leche->fresh()->stock_actual);
    }

    public function test_rechaza_receta_desactualizada_unidades_inexistentes_y_datos_invalidos_sin_escribir(): void
    {
        $original = $this->datos();
        foreach (['version','unidad','duplicada','insumo','negativo','precision'] as $caso) {
            $d = $original;
            if($caso==='version') $d['detalles'][0]['indicaciones'][0]['version']=str_repeat('x',64);
            if($caso==='unidad') $d['detalles'][0]['indicaciones'][0]['unidad']=3;
            if($caso==='duplicada') $d['detalles'][0]['indicaciones'][]=$d['detalles'][0]['indicaciones'][0];
            if($caso==='insumo') $d['detalles'][0]['indicaciones'][0]['insumos'][0]['insumo_id']=99999;
            if($caso==='negativo') $d['detalles'][0]['indicaciones'][0]['insumos'][0]['cantidad']=-1;
            if($caso==='precision') $d['detalles'][0]['indicaciones'][0]['insumos'][0]['cantidad']='0.0001';
            $this->postJson('/ventas',$d)->assertUnprocessable();
        }
        $this->assertDatabaseCount('ventas',0);
        $this->assertDatabaseCount('pedidos',0);
        $this->assertDatabaseCount('movimientos_inventario',0);
        $this->assertSame('2000.000',$this->leche->fresh()->stock_actual);
    }

    public function test_anulacion_cancela_cola_restaura_una_vez_y_reportes_no_duplican(): void
    {
        $this->postJson('/ventas',$this->datos())->assertOk();
        $v = Venta::first();
        $f = DB::table('detalle_pedidos')->first();
        $this->flushSession();
        $this->actingAs($this->usuario('Administrador'));
        $this->get('/reportes')->assertOk()->assertViewHas('total',fn($v)=>(float)$v===30.0);
        $this->get('/punto-de-venta?pedido='.$f->pedido_id)->assertNotFound();
        $this->post('/ventas/'.$v->id.'/anular',['motivo'=>'Cancelación cliente'])->assertSessionHasNoErrors();
        $this->post('/ventas/'.$v->id.'/anular',['motivo'=>'Cancelación cliente'])->assertSessionHasNoErrors();
        $this->postJson('/pedidos/'.$f->pedido_id.'/estado',['detalle_id'=>$f->id,'estado'=>'en_preparacion'])->assertUnprocessable();
        $this->get('/pedidos?estado=cancelado')->assertOk()->assertSee('Cancelada')->assertDontSee('Iniciar preparación');
        $this->assertSame('2000.000',$this->leche->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario',2);
        $this->assertDatabaseCount('ventas',1);
        $this->get('/reportes')->assertViewHas('total',fn($v)=>(float)$v===0.0);
    }
}
