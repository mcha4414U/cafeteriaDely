<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_compras', fn (Blueprint $table) => $table->string('unidad_medida', 100)->nullable()->change());
    }

    public function down(): void
    {
        // Conservar longitud para evitar truncar unidades ya registradas en compras.
    }
};
