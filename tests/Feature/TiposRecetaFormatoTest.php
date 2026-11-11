<?php

namespace Tests\Feature;

use App\Models\{Producto, Rol, Usuario, Cliente, Venta};
use App\Support\{Cantidad, Moneda};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TiposRecetaFormatoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        $u = Usuario::create(['nombre'=>'Administración','email'=>'formatos@example.test','password'=>'ClavePrueba123','estado'=>true]);
        $u->roles()->attach(Rol::where('nombre','Administrador')->firstOrFail());
        $this->actingAs($u);
    }

    private function datos(array $cambios=[]): array
    {
        return $cambios + ['nombre'=>'Producto prueba','tipo'=>'venta','requiere_receta'=>false,'precio_venta'=>'15.00','precio_compra'=>'3.00','stock_actual'=>'2000.250','stock_minimo'=>'10.000','unidad_medida'=>'unidad','estado'=>1];
    }

    private function crear(array $cambios=[]): Producto
    {
        $ruta = ($cambios['tipo'] ?? 'venta') === 'insumo' ? '/insumos' : '/productos';
        $this->post($ruta,$this->datos($cambios))->assertSessionHasNoErrors()->assertRedirect($ruta);
        return Producto::latest('id')->firstOrFail();
    }

    private function cobrar(Producto $p): array
    {
        return ['clave_idempotencia'=>(string)Str::uuid(),'cliente_id'=>Cliente::where('es_generico',true)->value('id'),
            'metodo_pago_id'=>DB::table('metodos_pago')->where('nombre','Efectivo')->value('id'),
            'descuento'=>0,'recibido'=>20,'detalles'=>[['producto_id'=>$p->id,'cantidad'=>1,'precio_esperado'=>$p->precio_venta]]];
    }

    public function test_insumo_no_exige_precio_venta_y_ignora_indicador_incompatible(): void
    {
        $d=$this->datos(['nombre'=>'Leche','tipo'=>'insumo','requiere_receta'=>true,'unidad_medida'=>'ml']);
        unset($d['precio_venta']);
        $this->post('/insumos',$d)->assertSessionHasNoErrors();
        $p=Producto::latest('id')->firstOrFail();
        $this->assertFalse($p->requiereReceta());
        $this->assertFalse($p->requiere_receta);
        $this->assertSame('0.00',$p->precio_venta);
        $this->get('/punto-de-venta')->assertOk()->assertDontSee('Leche');
        $this->postJson('/ventas',$this->cobrar($p))->assertUnprocessable();
    }

    public function test_producto_sin_receta_se_vende_y_comprobante_localiza_sin_cambiar_importes(): void
    {
        $p=$this->crear();
        $url=$this->postJson('/ventas',$this->cobrar($p))->assertOk()->json('url');
        $this->assertDatabaseCount('pedidos',0);
        $this->assertSame('1999.250',$p->fresh()->stock_actual);
        $this->get($url)->assertOk()->assertSee('Bs. 15,00')->assertSee('<td>1</td>',false);
        $this->assertSame('15.00',Venta::first()->total);
        $this->get('/reportes')->assertOk()->assertViewHas('total',fn($v)=>(float)$v===15.0);
    }

    public function test_requiere_receta_bloquea_cobro_hasta_configurar_y_luego_genera_preparacion(): void
    {
        $p=$this->crear(['requiere_receta'=>true,'stock_actual'=>0]);
        $this->postJson('/ventas',$this->cobrar($p))->assertUnprocessable()->assertSee('Falta registrar la receta');
        $this->assertDatabaseCount('ventas',0);
        $i=$this->crear(['tipo'=>'insumo','nombre'=>'Leche','unidad_medida'=>'ml','stock_actual'=>1000]);
        $this->put('/productos/'.$p->id.'/receta',['nombre'=>'Capuchino','estado'=>1,'detalles'=>[['insumo_id'=>$i->id,'cantidad'=>'150.125','unidad_medida'=>'ml']]])->assertSessionHasNoErrors();
        $this->postJson('/ventas',$this->cobrar($p))->assertOk();
        $this->get('/pedidos')->assertOk()->assertSee('150,125 ml');
        $this->assertSame('849.875',$i->fresh()->stock_actual);
        $this->assertDatabaseCount('ventas',1);
        $this->assertDatabaseCount('detalle_pedidos',1);
        $this->assertDatabaseHas('detalle_recetas',['cantidad'=>'150.125']);
    }

    public function test_no_desactiva_receta_existente_ni_convierte_tipo_automaticamente(): void
    {
        $p=$this->crear(['tipo'=>'ambos','requiere_receta'=>true]);
        $p->receta()->create(['nombre'=>'Existente','estado'=>true]);
        $this->put('/productos/'.$p->id,$this->datos(['tipo'=>'ambos','requiere_receta'=>false]))->assertSessionHasErrors('requiere_receta');
        $this->assertSame('ambos',$p->fresh()->tipo);
        $this->assertDatabaseCount('recetas',1);
        $this->get('/productos/'.$p->id.'/editar')->assertOk()->assertSee('Producto e insumo (existente)');
    }

    public function test_formularios_y_vistas_conservan_decimales_reales_y_cantidad_original(): void
    {
        $p=$this->crear(['tipo'=>'insumo','nombre'=>'Azúcar','unidad_medida'=>'kg','contenido_por_unidad'=>'1.500','presentacion'=>'Bolsa']);
        $antes=$p->getAttributes();
        $movimientos=DB::table('movimientos_inventario')->count();
        $this->get('/insumos/'.$p->id.'/editar')->assertOk()->assertSee('value="2000.25"',false)->assertSee('value="1.5"',false);
        $this->get('/insumos/'.$p->id)->assertOk()->assertSee('2.000,25 kg')->assertSee('1,5 kg');
        $this->get('/inventario')->assertOk()->assertSee('2.000,25');
        $this->get('/inventario/movimientos')->assertOk()->assertSee('2.000,25');
        $this->assertSame($antes,$p->fresh()->getAttributes());
        $this->assertSame($movimientos,DB::table('movimientos_inventario')->count());
        $d=$this->datos(['tipo'=>'insumo','nombre'=>'Azúcar','unidad_medida'=>'kg','contenido_por_unidad'=>'1.5','presentacion'=>'Bolsa']);
        unset($d['precio_venta']);
        $this->put('/insumos/'.$p->id,$d)->assertSessionHasNoErrors();
        $this->assertSame('2000.250',$p->fresh()->stock_actual);
        $this->assertSame('Bolsa',$p->fresh()->presentacion);
        $this->assertSame($movimientos,DB::table('movimientos_inventario')->count());
    }

    public function test_compatibilidad_null_no_cambia_productos_ni_recetas_anteriores(): void
    {
        $p=Producto::create($this->datos(['requiere_receta'=>null]));
        $this->assertFalse($p->requiereReceta());
        $p->receta()->create(['nombre'=>'Anterior','estado'=>true]);
        $this->assertTrue($p->fresh()->requiereReceta());
        $this->assertNull($p->fresh()->requiere_receta);
        $this->assertSame('2000.250',$p->fresh()->stock_actual);
    }

    public function test_formatos_separan_miles_decimales_inputs_y_dinero(): void
    {
        foreach(['2.000'=>'2','2000'=>'2.000','1.500'=>'1,5','0.250'=>'0,25','150.125'=>'150,125','23.841437'=>'23,841437'] as $valor=>$esperado) {
            $this->assertSame($esperado,Cantidad::mostrar($valor));
        }
        $this->assertSame('2000.25',Cantidad::entrada('2000.250'));
        $this->assertSame('0',Cantidad::entrada('0.000'));
        $this->assertSame('15,00',Moneda::mostrar('15.00'));
        $this->assertSame('2.000,25',Moneda::mostrar('2000.25'));
    }
}
