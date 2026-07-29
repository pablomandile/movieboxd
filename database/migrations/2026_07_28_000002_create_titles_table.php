<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['movie', 'tv']);
            $table->unsignedInteger('tmdb_id');
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('original_title')->nullable();
            $table->json('translations')->nullable();
            $table->text('overview')->nullable();
            $table->string('tagline')->nullable();
            $table->string('poster_path', 64)->nullable();
            $table->string('backdrop_path', 64)->nullable();
            $table->date('release_date')->nullable();
            $table->unsignedSmallInteger('runtime')->nullable();
            $table->json('genres')->nullable();
            $table->json('credits')->nullable();
            $table->char('original_language', 2)->nullable();
            $table->decimal('popularity', 8, 3)->default(0);
            $table->string('tv_status')->nullable();
            $table->date('last_air_date')->nullable();
            $table->unsignedSmallInteger('seasons_count')->nullable();
            $table->unsignedSmallInteger('episodes_count')->nullable();

            // Agregados cacheados (mantenidos por observers desde F3)
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('ratings_sum')->default(0);
            $table->json('ratings_histogram')->nullable();
            $table->unsignedInteger('watched_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('watchlist_count')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'tmdb_id']);
            $table->index('release_date');
            $table->index('popularity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titles');
    }
};
