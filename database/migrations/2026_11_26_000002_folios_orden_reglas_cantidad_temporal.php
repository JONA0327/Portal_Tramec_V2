<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Último folio descargado por serie, se guarde o no la cotización en el historial.
        if (! Schema::hasTable('cotizacion_folios')) {
            Schema::create('cotizacion_folios', function (Blueprint $table) {
                $table->string('serie', 20)->primary();
                $table->unsignedInteger('ultimo');
            });
        }

        // Posición de la regla: gana la de más arriba. Se parte del orden que ya regía (usuario > nivel > todos).
        if (! Schema::hasColumn('price_rules', 'orden')) {
            Schema::table('price_rules', fn (Blueprint $t) => $t->unsignedInteger('orden')->default(0));

            $grado = fn ($r) => ! empty(json_decode((string) $r->usuarios, true)) ? 2 : (! empty(json_decode((string) $r->niveles, true)) ? 1 : 0);

            DB::table('price_rules')->orderBy('id')->get()->sortByDesc($grado)->values()
                ->each(fn ($r, $i) => DB::table('price_rules')->where('id', $r->id)->update(['orden' => $i + 1]));
        }

        // Cantidad editada a mano por el administrador mientras no se resincronice (no toca el ERP).
        if (! Schema::hasColumn('existencia_overrides', 'cantidad_temporal')) {
            Schema::table('existencia_overrides', fn (Blueprint $t) => $t->decimal('cantidad_temporal', 12, 2)->nullable());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_folios');
        Schema::table('price_rules', fn (Blueprint $t) => $t->dropColumn('orden'));
        Schema::table('existencia_overrides', fn (Blueprint $t) => $t->dropColumn('cantidad_temporal'));
    }
};
