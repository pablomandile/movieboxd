<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mismo patrón que titles: snapshot local que se importa on demand,
        // la primera vez que alguien visita a esa persona.
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tmdb_id')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('profile_path')->nullable();
            $table->json('translations')->nullable();   // biografía es/en
            $table->text('biography')->nullable();
            $table->date('birthday')->nullable();
            $table->date('deathday')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('known_for_department')->nullable();
            $table->json('credits')->nullable();        // filmografía agrupada por rol
            $table->float('popularity')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
