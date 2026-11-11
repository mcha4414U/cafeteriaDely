<?php

namespace Tests\Feature;

use App\Models\{Producto, Usuario, Rol, Proveedor, Compra, Cliente, Venta};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PresentacionesInsumosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        config(['services.whatsapp.enabled' => false]);
        $u = Usuario::create(['nombre'=>'Admin','email'=>'envases@example.test','password'=>'Clave123456','estado'=>true]);
        $u->roles()->attach(Rol::where('nombre','Administrador')->firstOrFail());
        $this->actingAs($u);
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + ['nombre'=>'Leche Delizia','tipo'=>'insumo','unidad_medida'=>'ml','contenido_por_unidad'=>'946',
            'presentacion'=>'bolsa','unidades_stock'=>'24','precio_venta'=>0,'precio_compra'=>0,'stock_minimo'=>1000,'estado'=>1];
    }

    private function leche(): Producto
    {
        $this->post('/insumos', $this->datos())->assertSessionHasNoErrors()->assertRedirect();
        return Producto::where('nombre','Leche Delizia')->firstOrFail();
    }

    public function test_presentaciones_normales_personalizadas_y_compatibilidad_al_editar(): void
    {
        $p = $this->leche();
        $this->get('/insumos/'.$p->id.'/editar')->assertOk()->assertSee('data-presentacion-opciones', false);
        $datos = $this->datos(['presentacion'=>'Otro', 'otra_presentacion'=>'  Envase retornable  ', 'stock_actual'=>22704]);
        unset($datos['unidades_stock']);
        $this->put('/insumos/'.$p->id, $datos)->assertSessionHasNoErrors();
        $this->assertSame('Envase retornable', $p->fresh()->presentacion);
        $this->get('/insumos/'.$p->id.'/editar')->assertOk()->assertSee('value="Otro" selected', false)->assertSee('Envase retornable');
        $this->put('/insumos/'.$p->id, array_replace($datos, ['presentacion'=>'Botella', 'otra_presentacion'=>'Texto anterior']))->assertSessionHasNoErrors();
        $this->assertSame('Botella', $p->fresh()->presentacion);
        $this->assertSame('22704.000', $p->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->post('/insumos', $this->datos(['nombre'=>'Azúcar', 'presentacion'=>'Otro', 'otra_presentacion'=>'Sobre individual']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['nombre'=>'Azúcar', 'presentacion'=>'Sobre individual']);
        $p->update(['unidad_medida'=>'litro', 'presentacion'=>'bolsa']);
        $this->get('/insumos/'.$p->id.'/editar')->assertOk()->assertSee('litro (actual)');
        $this->put('/insumos/'.$p->id, array_replace($datos, ['unidad_medida'=>'litro', 'presentacion'=>'bolsa']))->assertSessionHasNoErrors();
        $this->assertSame('bolsa', $p->fresh()->presentacion);
        $this->assertSame('litro', $p->fresh()->unidad_medida);
    }

    public function test_rechaza_opciones_invalidas_y_presentaciones_vacias(): void
    {
        foreach ([['unidad_medida'=>'galón'], ['presentacion'=>'Inventada'], ['presentacion'=>'Otro'], ['presentacion'=>'Otro','otra_presentacion'=>'   '], ['presentacion'=>'Otro','otra_presentacion'=>str_repeat('a',101)]] as $datos) {
            $this->postJson('/insumos', $this->datos($datos))->assertUnprocessable();
        }
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_carga_calcula_en_servidor_y_edicion_no_reinterpreta_stock(): void
    {
        $p = $this->leche();
        $this->assertSame('22704.000', $p->stock_actual);
        $this->assertSame('24.000000', $p->unidadesEquivalentes());
        $this->assertDatabaseHas('movimientos_inventario', ['producto_id'=>$p->id,'cantidad'=>22704]);
        $datos = $this->datos(['contenido_por_unidad'=>1000,'stock_actual'=>'22704','stock_esperado'=>'22704']);
        unset($datos['unidades_stock']);
        $this->put('/insumos/'.$p->id, $datos)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('22704.000', $p->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        foreach (['/insumos/'.$p->id, '/insumos/'.$p->id.'/editar', '/inventario', '/recomendaciones', '/inventario/movimientos'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_ajuste_por_envases_exige_motivo_y_detecta_stock_desactualizado(): void
    {
        $p = $this->leche();
        $d = $this->datos(['unidades_stock'=>23,'stock_actual'=>0,'stock_esperado'=>'22704']);
        $this->put('/insumos/'.$p->id,$d)->assertSessionHasErrors('stock_actual');
        $d['motivo_stock'] = 'Conteo físico';
        $d['stock_esperado'] = 1;
        $this->put('/insumos/'.$p->id,$d)->assertSessionHasErrors('stock_nuevo');
        $d['stock_esperado'] = '22704';
        $this->put('/insumos/'.$p->id,$d)->assertSessionHasNoErrors();
        $this->assertSame('21758.000',$p->fresh()->stock_actual);
    }

    public function test_conversion_rechaza_cero_negativos_exceso_y_precision_perdida(): void
    {
        foreach ([
            ['contenido_por_unidad'=>0], ['unidades_stock'=>-1],
            ['contenido_por_unidad'=>'999999999','unidades_stock'=>24],
            ['contenido_por_unidad'=>'0.001','unidades_stock'=>'0.001'],
            ['tipo'=>'venta'],
        ] as $d) {
            $this->postJson('/insumos',$this->datos($d))->assertUnprocessable();
        }
        $this->assertDatabaseCount('productos',0);
    }

    public function test_compra_por_envases_conserva_costo_y_reintentos_no_duplican(): void
    {
        $p = $this->leche();
        $proveedor = Proveedor::create(['nombre'=>'Distribuidor','estado'=>true]);
        $d = ['clave_idempotencia'=>(string) Str::uuid(), 'proveedor_id'=>$proveedor->id, 'fecha'=>today()->toDateString(),
            'detalles'=>[['producto_id'=>$p->id,'cantidad'=>2,'costo_unitario'=>'6.50','modo_cantidad'=>'envases','contenido_esperado'=>'946']]];
        $this->post('/compras',$d)->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/compras',$d)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('compras',1);
        $this->assertSame('24596.000',$p->fresh()->stock_actual);
        $this->assertSame('6.50',$p->fresh()->precio_compra_presentacion);
        $compra = Compra::firstOrFail();
        $this->assertSame('13.00',$compra->total);
        $this->assertDatabaseHas('detalle_compras',['compra_id'=>$compra->id,'cantidad'=>2,'cantidad_stock'=>1892,'contenido_por_unidad'=>946]);
        $this->assertDatabaseHas('movimientos_inventario',['compra_id'=>$compra->id,'cantidad'=>1892]);
        $p->update(['contenido_por_unidad'=>1000]);
        $this->get('/compras/'.$compra->id)->assertOk()->assertSee('946')->assertSee('1.892');
        $this->post('/compras',$d)->assertSessionHasNoErrors();
        $d['clave_idempotencia'] = (string) Str::uuid();
        $this->post('/compras',$d)->assertSessionHasErrors('detalles');
        $this->assertSame('24596.000',$p->fresh()->stock_actual);
        $this->get('/compras/crear')->assertOk();
    }

    public function test_receta_descuenta_150_ml_y_anulacion_devuelve_contenido_original(): void
    {
        $leche = $this->leche();
        $p = Producto::create(['nombre'=>'Capuchino','tipo'=>'venta','estado'=>true,'precio_venta'=>16,'stock_actual'=>0,'stock_minimo'=>0,'unidad_medida'=>'unidad']);
        $r = $p->receta()->create(['nombre'=>'Capuchino','estado'=>true]);
        $r->detalles()->create(['insumo_id'=>$leche->id,'cantidad'=>150,'unidad_medida'=>'ml']);
        $d = ['clave_idempotencia'=>(string) Str::uuid(),'cliente_id'=>Cliente::where('es_generico',true)->value('id'),
            'metodo_pago_id'=>DB::table('metodos_pago')->where('nombre','Efectivo')->value('id'),'descuento'=>0,'recibido'=>20,
            'detalles'=>[['producto_id'=>$p->id,'cantidad'=>1,'precio_esperado'=>16]]];
        $this->postJson('/ventas',$d)->assertOk();
        $this->assertSame('22554.000',$leche->fresh()->stock_actual);
        $this->assertSame('23.841437',$leche->fresh()->unidadesEquivalentes());
        $this->assertDatabaseHas('movimientos_inventario',['producto_id'=>$leche->id,'tipo'=>'salida_venta','cantidad'=>150]);
        $this->get('/productos/'.$p->id.'/receta')->assertOk();
        $leche->update(['contenido_por_unidad'=>1000]);
        $this->post('/ventas/'.Venta::firstOrFail()->id.'/anular',['motivo'=>'Corrección de prueba'])->assertRedirect();
        $this->assertSame('22704.000',$leche->fresh()->stock_actual);
        $this->assertSame('2',$leche->fresh()->envasesPara('1000.001'));
    }

    public function test_insumos_anteriores_y_ventas_directas_conservan_unidades(): void
    {
        $d = $this->datos(['stock_actual'=>24]);
        unset($d['contenido_por_unidad'],$d['presentacion'],$d['unidades_stock']);
        $this->post(($d['tipo'] === 'insumo' ? '/insumos' : '/productos'),$d)->assertSessionHasNoErrors();
        $p = Producto::firstOrFail();
        $this->assertSame('24.000',$p->stock_actual);
        $this->assertNull($p->contenido_por_unidad);
        $d['tipo']='venta';
        $d['nombre']='Agua embotellada';
        $d['unidad_medida']='unidad';
        $this->post(($d['tipo'] === 'insumo' ? '/insumos' : '/productos'),$d)->assertSessionHasNoErrors();
        $this->assertSame('24.000',Producto::where('nombre','Agua embotellada')->firstOrFail()->stock_actual);
    }
}
