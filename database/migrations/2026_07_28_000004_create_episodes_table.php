<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            // Denormalizado: acelera queries de progreso por serie
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tmdb_id');
            $table->unsignedSmallInteger('season_number');
            $table->unsignedSmallInteger('episode_number');
            $table->string('name');
            $table->text('overview')->nullable();
            $table->json('translations')->nullable();
            $table->string('still_path', 64)->nullable();
            $table->date('air_date')->nullable();
            $table->unsignedSmallInteger('runtime')->nullable();

            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('ratings_sum')->default(0);
            $table->json('ratings_histogram')->nullable();
            $table->unsignedInteger('reviews_count')->default(0);

            $table->timestamps();

            $table->unique(['season_id', 'episode_number']);
            $table->index('title_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
