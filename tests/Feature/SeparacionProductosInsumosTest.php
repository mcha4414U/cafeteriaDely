<?php

namespace Tests\Feature;

use App\Models\{Cliente, Producto, Proveedor, Rol, Usuario, Venta};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeparacionProductosInsumosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        config(['services.whatsapp.enabled' => false]);
        $usuario = Usuario::create(['nombre' => 'Administración', 'email' => 'separacion@example.test', 'password' => 'ClavePrueba123', 'estado' => true]);
        $usuario->roles()->attach(Rol::where('nombre', 'Administrador')->firstOrFail());
        $this->actingAs($usuario);
    }

    private function datos(string $tipo, array $extra = []): array
    {
        return $extra + ['nombre' => 'Artículo '.Str::uuid(), 'codigo' => (string) Str::uuid(), 'tipo' => $tipo,
            'precio_venta' => 10, 'precio_compra' => 1, 'stock_actual' => 10, 'stock_minimo' => 1,
            'unidad_medida' => 'unidad', 'estado' => 1];
    }

    private function crear(string $tipo, array $extra = []): Producto
    {
        $ruta = $tipo === 'insumo' ? '/insumos' : '/productos';
        $this->post($ruta, $this->datos($tipo, $extra))->assertSessionHasNoErrors()->assertRedirect($ruta);
        return Producto::latest('id')->firstOrFail();
    }

    public function test_listados_separados_y_ambos_comparten_un_solo_registro(): void
    {
        $venta = $this->crear('venta', ['nombre' => 'Agua terminada']);
        $insumo = $this->crear('insumo', ['nombre' => 'Harina interna']);
        $ambos = $this->crear('ambos', ['nombre' => 'Artículo de uso dual']);
        $this->get('/productos')->assertOk()->assertViewHas('productos', fn ($p) => $p->modelKeys() === [$venta->id, $ambos->id])
            ->assertDontSee('Harina interna')->assertSee('href="'.route('insumos.index').'"', false);
        $this->get('/insumos')->assertOk()->assertViewHas('productos', fn ($p) => $p->modelKeys() === [$ambos->id, $insumo->id])
            ->assertDontSee('Agua terminada')->assertSee('Nuevo insumo');
        $this->assertDatabaseCount('productos', 3);
        $this->get('/punto-de-venta')->assertOk()->assertSee('Agua terminada')->assertSee('Artículo de uso dual')->assertDontSee('Harina interna');
        $this->get('/productos/'.$insumo->id)->assertRedirect('/insumos/'.$insumo->id);
        $this->get('/productos/'.$insumo->id.'/editar')->assertRedirect('/insumos/'.$insumo->id.'/editar');
        $this->get('/insumos/'.$venta->id)->assertNotFound();
    }

    public function test_formularios_y_validacion_impiden_creacion_en_modulo_equivocado(): void
    {
        $this->get('/insumos/crear')->assertOk()->assertSee('Nuevo insumo')->assertSee('action="'.url('/insumos').'"', false);
        $this->get('/productos/crear')->assertOk()->assertDontSee('<option value="insumo"', false);
        $this->post('/productos', $this->datos('insumo'))->assertSessionHasErrors('tipo');
        $this->post('/insumos', $this->datos('venta'))->assertSessionHasErrors('tipo');
        $this->post('/insumos', $this->datos('insumo', ['unidad_medida' => null]))->assertSessionHasErrors('unidad_medida');
        $this->post('/insumos', $this->datos('insumo', ['stock_actual' => -1]))->assertSessionHasErrors('stock_actual');
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_codigo_unico_es_compartido_y_edicion_no_duplica_movimientos(): void
    {
        $p = $this->crear('insumo', ['codigo' => 'UNICO']);
        $this->post('/productos', $this->datos('venta', ['codigo' => 'UNICO']))->assertSessionHasErrors('codigo');
        $antes = DB::table('movimientos_inventario')->count();
        $this->put('/insumos/'.$p->id, $this->datos('insumo', ['codigo' => 'UNICO', 'nombre' => 'Nombre actualizado']))
            ->assertSessionHasNoErrors()->assertRedirect('/insumos');
        $this->assertDatabaseCount('productos', 1);
        $this->assertDatabaseCount('movimientos_inventario', $antes);
        $this->assertSame('10.000', $p->fresh()->stock_actual);
        $this->put('/productos/'.$p->id, $this->datos('venta'))->assertNotFound();
        $this->assertSame('insumo', $p->fresh()->tipo);
    }

    public function test_archivar_restaurar_y_filtrar_insumo_conserva_identidad(): void
    {
        $p = $this->crear('insumo');
        $this->delete('/insumos/'.$p->id)->assertRedirect('/insumos');
        $this->get('/insumos?estado=archivado')->assertOk()->assertViewHas('productos', fn ($q) => $q->modelKeys() === [$p->id]);
        $this->get('/productos?estado=archivado')->assertOk()->assertViewHas('productos', fn ($q) => $q->isEmpty());
        $this->post('/insumos/'.$p->id.'/restaurar')->assertRedirect('/insumos');
        $this->assertDatabaseCount('productos', 1);
        $this->assertFalse($p->fresh()->trashed());
    }

    public function test_compra_mixta_suma_base_y_envases_una_sola_vez(): void
    {
        $p = $this->crear('venta');
        $i = $this->crear('insumo', ['unidad_medida' => 'ml', 'stock_actual' => 0, 'contenido_por_unidad' => 946, 'presentacion' => 'bolsa']);
        $proveedor = Proveedor::create(['nombre' => 'Proveedor', 'estado' => true]);
        $this->get('/compras/crear')->assertOk()->assertSee('Artículos recibidos')->assertSee('Agregar artículo')
            ->assertViewHas('productos', fn ($q) => $q->contains('id', $p->id) && $q->contains('id', $i->id));
        $datos = ['clave_idempotencia' => (string) Str::uuid(), 'proveedor_id' => $proveedor->id, 'fecha' => today()->toDateString(),
            'detalles' => [
                ['producto_id' => $p->id, 'cantidad' => 3, 'costo_unitario' => 2],
                ['producto_id' => $i->id, 'cantidad' => 2, 'costo_unitario' => 6, 'modo_cantidad' => 'envases', 'contenido_esperado' => 946],
            ]];
        $this->post('/compras', $datos)->assertSessionHasNoErrors();
        $this->post('/compras', $datos)->assertSessionHasNoErrors();
        $this->assertSame('13.000', $p->fresh()->stock_actual);
        $this->assertSame('1892.000', $i->fresh()->stock_actual);
        $this->assertDatabaseCount('compras', 1);
        $this->assertSame(2, DB::table('movimientos_inventario')->where('tipo', 'entrada_compra')->count());
        $datos['clave_idempotencia'] = (string) Str::uuid();
        $datos['detalles'][] = $datos['detalles'][0];
        $this->post('/compras', $datos)->assertSessionHasErrors('detalles.0.producto_id');
        $this->assertDatabaseCount('compras', 1);
    }

    public function test_uso_dual_venta_y_receta_agrega_consumo_sin_doble_descontar(): void
    {
        $i = $this->crear('ambos');
        $p = $this->crear('venta', ['stock_actual' => 0]);
        $receta = ['nombre' => 'Preparado', 'estado' => 1,
            'detalles' => [['insumo_id' => $i->id, 'cantidad' => 2, 'unidad_medida' => 'kg']]];
        $this->put('/productos/'.$p->id.'/receta', $receta)->assertSessionHasErrors('detalles.0.unidad_medida');
        $receta['detalles'][0]['unidad_medida'] = 'unidad';
        $this->put('/productos/'.$p->id.'/receta', $receta)->assertSessionHasNoErrors();
        $datos = ['clave_idempotencia' => (string) Str::uuid(), 'cliente_id' => Cliente::where('es_generico', true)->value('id'),
            'metodo_pago_id' => DB::table('metodos_pago')->where('nombre', 'Efectivo')->value('id'), 'descuento' => 0, 'recibido' => 20,
            'detalles' => [['producto_id' => $p->id, 'cantidad' => 1, 'precio_esperado' => 10], ['producto_id' => $i->id, 'cantidad' => 1, 'precio_esperado' => 10]]];
        $this->postJson('/ventas', $datos)->assertOk();
        $this->postJson('/ventas', $datos)->assertOk();
        $this->assertSame('7.000', $i->fresh()->stock_actual);
        $this->assertDatabaseCount('ventas', 1);
        $this->assertSame(1, DB::table('movimientos_inventario')->where('tipo', 'salida_venta')->count());
        $this->assertDatabaseHas('movimientos_inventario', ['producto_id' => $i->id, 'tipo' => 'salida_venta', 'cantidad' => 3]);
        $v = Venta::firstOrFail();
        $detalle = DB::table('detalle_pedidos')->where('pedido_id', $v->pedido_id)->first();
        foreach (['en_preparacion', 'listo'] as $estado) {
            $this->post('/pedidos/'.$v->pedido_id.'/estado', ['detalle_id' => $detalle->id, 'estado' => $estado])->assertSessionHasNoErrors();
        }
        $this->assertSame('7.000', $i->fresh()->stock_actual);
    }

    public function test_cajero_consulta_insumos_pero_no_gestiona_ni_ajusta(): void
    {
        $p = $this->crear('insumo');
        $u = Usuario::create(['nombre' => 'Caja', 'email' => 'caja-separada@example.test', 'password' => 'ClavePrueba123', 'estado' => true]);
        $u->roles()->attach(Rol::where('nombre', 'Cajero')->firstOrFail());
        $this->flushSession();
        $this->actingAs($u);
        $this->get('/insumos')->assertOk();
        $this->get('/insumos/crear')->assertForbidden();
        $this->post('/insumos', $this->datos('insumo'))->assertForbidden();
        $this->put('/insumos/'.$p->id, $this->datos('insumo'))->assertForbidden();
        $this->delete('/insumos/'.$p->id)->assertForbidden();
        $this->post('/insumos/'.$p->id.'/ajustar-stock', ['stock_nuevo' => 0, 'stock_esperado' => 10, 'motivo' => 'No autorizado'])->assertForbidden();
        $this->assertSame('10.000', $p->fresh()->stock_actual);
    }

    public function test_edicion_no_pierde_presentacion_por_habilitar_venta(): void
    {
        $p = $this->crear('insumo', ['unidad_medida' => 'ml', 'contenido_por_unidad' => 946, 'presentacion' => 'bolsa']);
        $this->put('/insumos/'.$p->id, $this->datos('ambos', ['unidad_medida' => 'ml']))->assertSessionHasErrors('tipo');
        $this->assertSame('insumo', $p->fresh()->tipo);
        $this->assertSame('946.000', $p->fresh()->contenido_por_unidad);
        $this->assertSame('10.000', $p->fresh()->stock_actual);
    }
}

