<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unique('nombre');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unique('nombre');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('permiso_rol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('permiso_id')->constrained('permisos')->restrictOnDelete();
            $table->unique(['rol_id', 'permiso_id']);
            $table->timestamps();
        });

        Schema::create('rol_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete();
            $table->unique(['usuario_id', 'rol_id']);
            $table->timestamps();
        });

        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unique('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('telefono', 30)->nullable();
            $table->text('direccion')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('es_generico')->default(false);
            $table->boolean('estado')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('empresa')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->text('direccion')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('estado')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->string('nombre');
            $table->index('nombre');
            $table->text('descripcion')->nullable();
            $table->string('codigo', 80)->unique();
            $table->decimal('precio_compra', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->decimal('stock_actual', 12, 3)->default(0);
            $table->decimal('stock_minimo', 12, 3)->default(0);
            $table->string('unidad_medida', 30)->default('unidad');
            $table->string('imagen')->nullable();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->boolean('estado')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unique('nombre');
            $table->boolean('estado')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('numero')->unique();
            $table->dateTime('fecha')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['registrada', 'anulada'])->default('registrada');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('detalle_compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('numero')->unique();
            $table->dateTime('fecha')->index();
            $table->enum('estado', ['pendiente', 'en_preparacion', 'completado', 'cancelado'])->default('pendiente')->index();
            $table->text('notas')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('detalle_pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->restrictOnDelete();
            $table->foreignId('pedido_id')->nullable()->unique()->constrained('pedidos')->restrictOnDelete();
            $table->string('numero')->unique();
            $table->uuid('clave_idempotencia')->unique();
            $table->dateTime('fecha')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['completada', 'anulada'])->default('completada')->index();
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('nombre_producto');
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('desperdicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->enum('motivo', ['vencido', 'danado', 'preparado_incorrectamente', 'desperdiciado', 'otro']);
            $table->dateTime('fecha')->index();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->enum('tipo', ['entrada_compra', 'salida_venta', 'salida_desperdicio', 'ajuste'])->index();
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->decimal('stock_anterior', 12, 3)->default(0);
            $table->decimal('stock_nuevo', 12, 3)->default(0);
            $table->string('motivo');
            $table->dateTime('fecha')->index();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->restrictOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->restrictOnDelete();
            $table->foreignId('desperdicio_id')->nullable()->constrained('desperdicios')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('alertas_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->enum('tipo_alerta', ['stock_bajo', 'agotado', 'proximo_vencimiento']);
            $table->text('mensaje');
            $table->boolean('enviada')->default(false);
            $table->dateTime('fecha_envio')->nullable();
            $table->enum('estado', ['activa', 'resuelta'])->default('activa');
            // Un único registro activo por producto/tipo; al resolver, la clave se vuelve NULL.
            $table->string('clave_activa')->nullable()->unique();
            $table->index(['producto_id', 'tipo_alerta', 'estado']);
            $table->timestamps();
        });

        Schema::create('recomendaciones_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad_sugerida', 12, 3)->default(0);
            $table->text('recomendacion');
            $table->text('motivo');
            $table->dateTime('fecha_generacion')->index();
            $table->enum('estado', ['pendiente', 'aceptada', 'descartada'])->default('pendiente');
            $table->timestamps();
        });

        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->text('valor')->nullable();
            $table->string('grupo')->default('general')->index();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
        Schema::dropIfExists('recomendaciones_ia');
        Schema::dropIfExists('alertas_stock');
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('desperdicios');
        Schema::dropIfExists('detalle_ventas');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('detalle_pedidos');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('detalle_compras');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('metodos_pago');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('rol_usuario');
        Schema::dropIfExists('permiso_rol');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
    }
};

