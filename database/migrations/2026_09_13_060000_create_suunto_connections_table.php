<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suunto_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Username akun Suunto App (klaim `user` di JWT) — dipakai untuk
            // menampilkan siapa yang terhubung, bukan untuk autentikasi.
            $table->string('suunto_username')->nullable();
            // Token disimpan terenkripsi (cast `encrypted` di model).
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->string('scope')->nullable();
            $table->dateTime('connected_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suunto_connections');
    }
};
