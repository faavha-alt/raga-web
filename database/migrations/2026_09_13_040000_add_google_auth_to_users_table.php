<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom untuk masuk dan daftar lewat Google.
     *
     * `google_id` menyimpan klaim `sub` dari Google dan dibuat unik supaya satu
     * identitas Google tidak bisa diklaim dua akun.
     *
     * `password` menjadi nullable karena akun yang lahir dari Google memang tidak
     * punya password. Kolom ini sekaligus menjadi satu-satunya sumber kebenaran
     * untuk pertanyaan "apakah akun ini punya password?", yang dipakai halaman
     * ganti password dan hapus akun untuk melewati verifikasi `current_password`.
     * Tanpa itu, pengguna Google akan terkunci permanen: `current_password` selalu
     * gagal karena tidak ada hash untuk dibandingkan, sehingga mereka tidak akan
     * pernah bisa memasang password sendiri atau menghapus akunnya.
     *
     * Foto profil Google tidak disimpan di kolom terpisah: berkasnya diunduh ke
     * penyimpanan lokal oleh App\Services\Auth\GoogleAccountService supaya
     * browser pengunjung tidak perlu memanggil server Google setiap kali sebuah
     * avatar dirender.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id', 64)->nullable()->unique()->after('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
