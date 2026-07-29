<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letterboxd_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('zip_path');
            $table->json('options'); // secciones elegidas
            $table->json('lists_meta')->nullable(); // definiciones de listas del export
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('matched')->default(0);
            $table->json('unmatched')->nullable(); // [{name, year, reason}]
            $table->json('summary')->nullable(); // conteos por sección
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('letterboxd_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('letterboxd_imports')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year')->nullable();
            $table->json('payload'); // datos agregados de la película en el export
            $table->enum('status', ['pending', 'matched', 'unmatched', 'failed'])->default('pending');
            $table->foreignId('title_id')->nullable()->constrained()->nullOnDelete();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letterboxd_import_items');
        Schema::dropIfExists('letterboxd_imports');
    }
};
