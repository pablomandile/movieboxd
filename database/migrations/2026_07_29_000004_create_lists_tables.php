<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_ranked')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['is_public', 'updated_at']);
        });

        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('lists')->cascadeOnDelete();
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['list_id', 'title_id']);
            $table->index(['list_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_items');
        Schema::dropIfExists('lists');
    }
};
