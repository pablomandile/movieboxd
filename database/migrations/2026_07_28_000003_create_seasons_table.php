<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tmdb_id');
            $table->unsignedSmallInteger('season_number');
            $table->string('name');
            $table->text('overview')->nullable();
            $table->json('translations')->nullable();
            $table->string('poster_path', 64)->nullable();
            $table->date('air_date')->nullable();
            $table->unsignedSmallInteger('episodes_count')->default(0);

            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('ratings_sum')->default(0);
            $table->json('ratings_histogram')->nullable();
            $table->unsignedInteger('reviews_count')->default(0);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['title_id', 'season_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
