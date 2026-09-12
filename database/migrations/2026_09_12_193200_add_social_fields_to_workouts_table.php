<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom sosial pada tabel aktivitas.
     *
     * `visibility` default 'private' dengan sengaja: seluruh aktivitas lama
     * (data Garmin pribadi berisi lokasi rumah) tidak boleh otomatis menjadi
     * publik hanya karena fitur sosial ditambahkan. Publikasi harus eksplisit.
     */
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->string('visibility', 20)->default('private')->after('source');
            $table->text('description')->nullable()->after('name');
            $table->string('location_name', 120)->nullable()->after('visibility');

            $table->index(['visibility', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'start_date']);
            $table->dropColumn(['visibility', 'description', 'location_name']);
        });
    }
};
