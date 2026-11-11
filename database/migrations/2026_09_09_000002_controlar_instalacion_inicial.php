<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones')->insertOrIgnore([
            'clave' => 'instalacion_completada', 'valor' => DB::table('usuarios')->exists() ? '1' : '0',
            'grupo' => 'sistema', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('configuraciones')->where('clave', 'instalacion_completada')->delete();
    }
};
