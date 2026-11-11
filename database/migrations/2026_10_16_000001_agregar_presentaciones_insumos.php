<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('contenido_por_unidad', 12, 3)->nullable();
            $table->decimal('precio_compra_presentacion', 12, 2)->nullable();
            $table->string('presentacion', 100)->nullable();
        });
        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->decimal('contenido_por_unidad', 12, 3)->nullable();
            $table->decimal('cantidad_stock', 12, 3)->nullable();
            $table->string('presentacion', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('detalle_compras', fn (Blueprint $table) => $table->dropColumn(['contenido_por_unidad', 'cantidad_stock', 'presentacion']));
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn(['contenido_por_unidad', 'precio_compra_presentacion', 'presentacion']));
    }
};
