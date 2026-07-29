<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reviewable'); // title | season | episode
            // Review atada a un log → un solo ítem en el feed (regla 4)
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries')->nullOnDelete();
            $table->text('body');
            $table->boolean('contains_spoilers')->default(false);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('commentable'); // review (listas en F6)
            $table->text('body');
            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id', 'created_at']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reportable'); // review | comment | user | list
            $table->enum('reason', ['spoiler', 'spam', 'abuse', 'other']);
            $table->text('details')->nullable();
            $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('reviews');
    }
};
