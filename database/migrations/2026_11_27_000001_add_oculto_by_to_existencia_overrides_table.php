<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Quién y cuándo ocultó a mano una existencia (updated_by cambia con cualquier otra edición).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('existencia_overrides', 'oculto_by')) {
            return;
        }

        Schema::table('existencia_overrides', function (Blueprint $table) {
            $table->unsignedBigInteger('oculto_by')->nullable(); // sin llave foránea: users.id viene de v1
            $table->timestamp('oculto_at')->nullable();
        });

        // Lo ya oculto: se toma el último que tocó la ficha.
        DB::table('existencia_overrides')->where('oculto', true)->update(['oculto_by' => DB::raw('updated_by'), 'oculto_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('existencia_overrides', fn (Blueprint $t) => $t->dropColumn(['oculto_by', 'oculto_at']));
    }
};
