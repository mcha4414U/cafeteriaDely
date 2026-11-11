<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas_stock', function (Blueprint $table) {
            $table->timestamp('ultimo_aviso_manual_at')->nullable();
            $table->foreignId('ultimo_aviso_manual_por')->nullable()->constrained('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alertas_stock', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ultimo_aviso_manual_por');
            $table->dropColumn('ultimo_aviso_manual_at');
        });
    }
};
