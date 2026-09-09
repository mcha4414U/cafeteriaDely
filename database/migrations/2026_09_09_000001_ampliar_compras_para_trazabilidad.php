<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->uuid('clave_idempotencia')->nullable()->unique();
            $table->string('huella_solicitud', 64)->nullable();
            $table->string('nombre_proveedor')->nullable();
        });
        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->string('nombre_producto')->nullable();
            $table->string('unidad_medida', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('detalle_compras', fn (Blueprint $table) => $table->dropColumn(['nombre_producto', 'unidad_medida']));
        Schema::table('compras', function (Blueprint $table) {
            $table->dropUnique(['clave_idempotencia']);
            $table->dropColumn(['clave_idempotencia', 'huella_solicitud', 'nombre_proveedor']);
        });
    }
};
