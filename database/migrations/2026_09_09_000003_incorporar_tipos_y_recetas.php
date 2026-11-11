<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('tipo', 10)->default('venta')->index();
            $table->unsignedBigInteger('categoria_id')->nullable()->change();
            $table->string('codigo', 100)->nullable()->change();
            $table->string('unidad_medida', 100)->nullable()->default('unidad')->change();
        });
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->unique()->constrained('productos')->restrictOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        Schema::create('detalle_recetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receta_id')->constrained('recetas')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->string('unidad_medida', 100);
            $table->timestamps();
            $table->unique(['receta_id', 'insumo_id']);
        });
    }

    public function down(): void
    {
        // Se conservan las ampliaciones de campos para no truncar códigos ni perder nulos.
        Schema::dropIfExists('detalle_recetas');
        Schema::dropIfExists('recetas');
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn('tipo'));
    }
};
