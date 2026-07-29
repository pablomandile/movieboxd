<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->unique()->after('name');
            $table->enum('role', ['user', 'admin'])->default('user')->after('password');
            $table->string('avatar_path')->nullable()->after('role');
            $table->text('bio')->nullable()->after('avatar_path');
            $table->char('locale', 2)->default('es')->after('bio');
            $table->timestamp('banned_at')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'avatar_path', 'bio', 'locale', 'banned_at']);
        });
    }
};
