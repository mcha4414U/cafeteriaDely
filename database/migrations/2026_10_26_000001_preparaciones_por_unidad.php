<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', fn (Blueprint $t) => $t->boolean('es_preparacion')->default(false)->index());
        Schema::table('detalle_pedidos', function (Blueprint $t) {
            $t->unsignedInteger('unidad_numero')->nullable();
            $t->json('preparacion')->nullable();
            $t->string('estado_preparacion', 20)->nullable()->index();
            $t->foreignId('actualizada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $t->unique(['pedido_id','producto_id','unidad_numero'], 'preparacion_unidad_unica');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_pedidos', function (Blueprint $t) {
            $t->dropUnique('preparacion_unidad_unica');
            $t->dropConstrainedForeignId('actualizada_por');
            $t->dropColumn(['unidad_numero','preparacion','estado_preparacion']);
        });
        Schema::table('pedidos', fn (Blueprint $t) => $t->dropColumn('es_preparacion'));
    }
};
