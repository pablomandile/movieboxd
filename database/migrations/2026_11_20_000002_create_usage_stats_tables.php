<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Última vez que la persona usó la app (lo anota TrackUserActivity)
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('banned_at');
        });

        // Una fila por usuario y día con actividad: usuarios activos por día,
        // por semana y días activos de cada uno.
        Schema::create('user_activity_days', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            $table->primary(['user_id', 'date']);
            $table->index('date');
        });

        // Contadores diarios de cosas que no dejan fila propia: pedidos a TMDB,
        // importaciones, bots frenados, lo que borró prune-catalog (ver App\Support\Metrics).
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->date('date');
            $table->string('metric', 64);
            $table->unsignedInteger('total')->default(0);

            $table->primary(['date', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
        Schema::dropIfExists('user_activity_days');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
