<?php

use App\Services\StockService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Productos dados de baja desde el portal (por empresa + código). Mientras estén aquí el portal
     * los ignora aunque CONTPAQi los siga devolviendo: no salen en Productos ni en Existencias, no
     * cuentan como "nuevos" y el Excel de Schenker los omite. No se toca CONTPAQi; se reactivan desde
     * la página Bajas (solo administradores). empresa = '*' cuando no se conoce la empresa (producto
     * local sin emparejar): aplica a cualquiera.
     */
    public function up(): void
    {
        Schema::create('producto_bajas', function (Blueprint $table) {
            $table->id();
            $table->string('empresa', 10);
            $table->string('codigo', 100);
            $table->unsignedInteger('id_erp')->nullable();
            $table->string('nombre', 191)->nullable();
            $table->unsignedBigInteger('dado_de_baja_por')->nullable();
            $table->timestamps();

            $table->unique(['empresa', 'codigo']);
        });

        // Las bajas anteriores eran por producto + almacén (existencia_overrides.de_baja): pasan a baja por producto.
        $catalogo = collect(app(StockService::class)->getProducts());

        DB::table('existencia_overrides')->where('de_baja', true)->get()->each(function ($o) use ($catalogo) {
            $producto = $catalogo->first(fn ($p) => ($p['empresa_codigo'] ?? 'TRAMEC') === $o->empresa && (int) $p['id'] === (int) $o->id_producto);
            $codigo = $producto['codigo'] ?? $o->codigo;

            if (! $codigo) {
                return; // no se puede identificar: se deja la marca vieja sin tocar
            }

            DB::table('producto_bajas')->insertOrIgnore([
                'empresa' => $o->empresa,
                'codigo' => strtoupper(trim($codigo)),
                'id_erp' => $producto['id'] ?? null,
                'nombre' => $producto['nombre'] ?? $o->nombre,
                'dado_de_baja_por' => $o->de_baja_by,
                'created_at' => $o->de_baja_at ?? now(),
                'updated_at' => now(),
            ]);

            DB::table('existencia_overrides')->where('id', $o->id)->update(['de_baja' => false, 'de_baja_at' => null]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_bajas');
    }
};
