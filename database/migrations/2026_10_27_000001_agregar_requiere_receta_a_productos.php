<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // NULL conserva el comportamiento anterior según la receta existente.
        // No cambia tipos, existencias, precios ni datos históricos.
        Schema::table('productos', fn (Blueprint $table) => $table->boolean('requiere_receta')->nullable());
    }

    public function down(): void
    {
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn('requiere_receta'));
    }
};
