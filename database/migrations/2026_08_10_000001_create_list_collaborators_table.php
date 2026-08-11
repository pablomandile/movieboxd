<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lists', function (Blueprint $table) {
            // Un único token por lista, compartible y regenerable. Null hasta que
            // el dueño genera la primera invitación.
            $table->string('invite_token', 40)->nullable()->unique()->after('is_public');
        });

        Schema::create('list_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('lists')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['list_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_collaborators');

        Schema::table('lists', function (Blueprint $table) {
            $table->dropColumn('invite_token');
        });
    }
};
