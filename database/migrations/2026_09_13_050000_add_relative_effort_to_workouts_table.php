<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skor Relative Effort (estimasi TRIMP berbasis zona HR) per aktivitas.
     *
     * Nullable dengan sengaja: aktivitas tanpa data HR per-detik (atau tanpa
     * data HR sama sekali) tidak bisa dihitung, dan `null` berarti "belum/tidak
     * bisa dihitung" — bukan nol. Berbeda dari `training_load` yang datang dari
     * Garmin, kolom ini dihitung RAGA sendiri dari `workout_samples`, sehingga
     * aktivitas hasil rekaman browser pun punya ukuran beban latihan.
     */
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->unsignedSmallInteger('relative_effort')->nullable()->after('training_load');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn('relative_effort');
        });
    }
};
