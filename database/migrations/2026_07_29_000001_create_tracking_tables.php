<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flag "visto" a nivel título — independiente del diario (regla 1)
        Schema::create('watched_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'title_id']);
            $table->index('title_id');
        });

        // Trackeo por episodio (extensión propia para series)
        Schema::create('episode_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            // Denormalizados para queries de progreso baratas
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->timestamp('watched_at');
            $table->timestamps();

            $table->unique(['user_id', 'episode_id']);
            $table->index(['user_id', 'title_id']);
            $table->index(['user_id', 'season_id']);
        });

        // Diario: eventos fechados, cada rewatch es una entrada nueva (regla 3)
        Schema::create('diary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('loggable'); // title | season | episode
            $table->date('watched_on');
            $table->unsignedTinyInteger('rating')->nullable(); // snapshot 1-10 al momento del log
            $table->boolean('is_rewatch')->default(false);
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'watched_on']);
            $table->index(['user_id', 'created_at']);
        });

        // Rating VIGENTE por usuario y sujeto (el diario guarda snapshots)
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('rateable'); // title | season | episode
            $table->unsignedTinyInteger('value'); // 1-10 = 0.5-5.0 estrellas
            $table->timestamps();

            $table->unique(['user_id', 'rateable_type', 'rateable_id']);
        });

        // Likes (corazón) — morph: title ahora; review/list/comment en fases siguientes
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('likeable');
            $table->timestamps();

            $table->unique(['user_id', 'likeable_type', 'likeable_id']);
        });

        // Watchlist única por usuario (regla 5)
        Schema::create('watchlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'title_id']);
            $table->index('title_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_items');
        Schema::dropIfExists('likes');
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('diary_entries');
        Schema::dropIfExists('episode_watches');
        Schema::dropIfExists('watched_titles');
    }
};
