<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('titles', function (Blueprint $table) {
            // Dónde se puede ver, ya filtrado a la región configurada (datos de JustWatch
            // vía TMDB). Guardar los 70 países infla el snapshot sin necesidad.
            $table->json('watch_providers')->nullable()->after('credits');

            // Los catálogos rotan mucho más rápido que la metadata, así que la
            // disponibilidad se refresca con su propio umbral, aparte de synced_at.
            $table->timestamp('watch_providers_synced_at')->nullable()->after('watch_providers');
        });
    }

    public function down(): void
    {
        Schema::table('titles', function (Blueprint $table) {
            $table->dropColumn(['watch_providers', 'watch_providers_synced_at']);
        });
    }
};
