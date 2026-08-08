<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email_verified_at');
        });

        // Quien entra con Google no tiene contraseña: la columna deja de ser obligatoria.
        // SQLite (tests) no soporta MODIFY: allí la columna se recrea sin NOT NULL.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('password')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            // Las filas sin contraseña bloquean el rollback: se les pone una inutilizable
            DB::table('users')->whereNull('password')->update(['password' => '']);
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
        }
    }
};
