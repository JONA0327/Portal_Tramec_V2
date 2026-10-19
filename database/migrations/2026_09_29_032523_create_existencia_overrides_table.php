<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajustes manuales sobre Existencias, SEPARADOS del caché del ERP
     * (stock_service.levels en Cache, ver StockLevelService) -- nunca se
     * mezclan: acá solo vive lo que alguien tocó a mano desde el portal.
     *
     * cantidad_erp_al_editar es la foto de lo que decía el ERP en el momento
     * de editar. Si el próximo sync trae un valor DISTINTO a esa foto, hay
     * conflicto (el ERP cambió después del ajuste) -- ver
     * ExistenciaOverride::tieneConflicto() y ExistenciasController::inconsistencias().
     */
    public function up(): void
    {
        Schema::create('existencia_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('empresa', 10);
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_almacen');
            $table->decimal('cantidad_manual', 12, 2)->nullable();
            $table->decimal('cantidad_erp_al_editar', 12, 2)->nullable();
            $table->boolean('oculto')->default(false);
            $table->boolean('de_baja')->default(false);
            $table->timestamp('de_baja_at')->nullable();
            // users.id es int unsigned (heredado del esquema de v1, no bigIncrements de
            // Laravel) -- foreignId() crea unsignedBigInteger y la FK no cuadra.
            $table->unsignedInteger('de_baja_by')->nullable();
            $table->foreign('de_baja_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa', 'id_producto', 'id_almacen']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('existencia_overrides');
    }
};
