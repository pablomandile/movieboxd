<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Última visita de una persona (no de un bot) a la ficha. Es lo que usa
        // movieboxd:prune-catalog para decidir qué snapshots de TMDB ya no sirven.
        // NULL = nunca se registró una visita: cuenta created_at.
        Schema::table('titles', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('synced_at');
            $table->index('viewed_at');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('synced_at');
            $table->index('viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('titles', function (Blueprint $table) {
            $table->dropIndex(['viewed_at']);
            $table->dropColumn('viewed_at');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['viewed_at']);
            $table->dropColumn('viewed_at');
        });
    }
};
