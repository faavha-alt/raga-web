<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suunto punya dua jalur: OAuth resmi (Partner Program) dan CLI tidak resmi
 * `suuntool` yang memakai backend aplikasi Suunto (login email/password).
 * `auth_mode` mencatat jalur mana yang dipakai, `email` menyimpan identitas
 * login untuk jalur tool (password TIDAK disimpan — hanya dipakai sekali saat
 * login, sesinya hidup di berkas terpisah milik proses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suunto_connections', function (Blueprint $table) {
            $table->string('auth_mode')->default('oauth')->after('user_id');
            $table->string('email')->nullable()->after('suunto_username');
        });
    }

    public function down(): void
    {
        Schema::table('suunto_connections', function (Blueprint $table) {
            $table->dropColumn(['auth_mode', 'email']);
        });
    }
};
