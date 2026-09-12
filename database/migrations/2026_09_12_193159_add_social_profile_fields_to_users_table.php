<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom profil publik untuk lapisan sosial.
     *
     * `username` sengaja nullable: tabel users di produksi sudah berisi data dan
     * menambah kolom unik non-null ke tabel terisi tidak aman dalam satu langkah.
     * Index unik tetap dibuat (NULL boleh berulang di MySQL maupun SQLite), dan
     * aplikasi mewajibkan username saat registrasi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique()->after('name');
            $table->string('avatar_path')->nullable()->after('username');
            $table->text('bio')->nullable()->after('avatar_path');
            $table->string('location', 100)->nullable()->after('bio');
            // Profil publik secara default, tetapi data kesehatan tetap privat
            // (tidak pernah dirender di permukaan sosial).
            $table->boolean('is_public')->default(true)->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'avatar_path', 'bio', 'location', 'is_public']);
        });
    }
};
