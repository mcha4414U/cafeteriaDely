<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->uuid('clave_idempotencia')->nullable()->unique();
            $t->string('huella_solicitud', 64)->nullable();
        });
        Schema::table('detalle_pedidos', fn (Blueprint $t) => $t->string('nombre_producto')->nullable());
        Schema::table('desperdicios', function (Blueprint $t) {
            $t->uuid('clave_idempotencia')->nullable()->unique();
            $t->string('huella_solicitud', 64)->nullable();
        });
        Schema::table('ventas', function (Blueprint $t) {
            $t->string('huella_solicitud', 64)->nullable();
            $t->string('nombre_cliente')->nullable();
            $t->string('nombre_metodo_pago')->nullable();
            $t->decimal('recibido', 12, 2)->nullable();
            $t->decimal('cambio', 12, 2)->default(0);
            $t->text('motivo_anulacion')->nullable();
            $t->foreignId('anulada_por')->nullable()->constrained('usuarios')->restrictOnDelete();
            $t->timestamp('anulada_at')->nullable();
        });
        Schema::table('alertas_stock', function (Blueprint $t) {
            $t->string('envio_estado', 30)->default('pendiente')->index();
            $t->string('mensaje_meta_id')->nullable()->index();
            $t->text('error_envio')->nullable();
            $t->timestamp('intento_at')->nullable();
            $t->unsignedBigInteger('meta_evento_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', fn (Blueprint $t) => $t->dropColumn(['clave_idempotencia', 'huella_solicitud']));
        Schema::table('detalle_pedidos', fn (Blueprint $t) => $t->dropColumn('nombre_producto'));
        Schema::table('desperdicios', fn (Blueprint $t) => $t->dropColumn(['clave_idempotencia', 'huella_solicitud']));
        Schema::table('ventas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('anulada_por');
            $t->dropColumn(['huella_solicitud', 'nombre_cliente', 'nombre_metodo_pago', 'recibido', 'cambio', 'motivo_anulacion', 'anulada_at']);
        });
        Schema::table('alertas_stock', fn (Blueprint $t) => $t->dropColumn(['envio_estado', 'mensaje_meta_id', 'error_envio', 'intento_at', 'meta_evento_at']));
    }
};
