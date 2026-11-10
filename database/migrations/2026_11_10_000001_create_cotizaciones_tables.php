<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carrito / cotizador: cada usuario con acceso tiene UNA cotización en borrador (sus datos generales) y los
     * renglones que fue agregando desde Productos y Existencias. Los precios se guardan como foto al agregar
     * (ya con las reglas de descuento/aumento que ve ese usuario) y el precio unitario se puede editar.
     */
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->date('fecha');
            $table->string('folio', 60)->nullable();
            $table->string('vigencia', 60)->nullable();
            $table->string('cliente_empresa', 191)->nullable();
            $table->string('cliente_atencion', 191)->nullable();
            $table->string('cliente_telefono', 60)->nullable();
            $table->string('condiciones_pago', 191)->nullable();
            $table->text('terminos')->nullable();
            $table->string('emisor', 191)->nullable();
            $table->string('emisor_email', 191)->nullable();
            $table->string('emisor_telefono', 60)->nullable();
            $table->string('precio_base', 30)->default('precio_lista'); // columna de precio que alimenta los renglones
            $table->decimal('descuento_pct', 5, 2)->default(0);
            $table->decimal('iva_pct', 5, 2)->default(16);
            $table->timestamps();
        });

        Schema::create('cotizacion_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotizacion_id')->index();
            $table->string('empresa', 10)->nullable();
            $table->string('codigo', 100);
            $table->string('descripcion', 500)->nullable();
            $table->string('moneda', 5)->default('MXN');
            $table->json('precios')->nullable();             // columna => precio, foto al agregar
            $table->decimal('precio_manual', 14, 2)->nullable(); // si se edita, manda sobre la columna elegida
            $table->decimal('cantidad', 12, 2)->default(1);
            $table->string('unidad', 20)->default('PZA.');
            $table->string('tiempo_entrega', 100)->nullable();
            $table->timestamps();

            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_items');
        Schema::dropIfExists('cotizaciones');
    }
};
