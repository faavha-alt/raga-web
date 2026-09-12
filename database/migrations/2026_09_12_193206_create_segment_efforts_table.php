<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu usaha (effort) melewati sebuah segment.
     *
     * Satu aktivitas menghasilkan maksimal satu effort per segment (yang terbaik),
     * sehingga leaderboard bisa langsung diurutkan lewat index komposit.
     */
    public function up(): void
    {
        Schema::create('segment_efforts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->double('elapsed_seconds');
            $table->double('moving_seconds')->nullable();
            $table->double('average_heart_rate')->nullable();
            $table->double('average_pace_seconds_per_km')->nullable();
            $table->boolean('is_personal_best')->default(false);
            $table->timestamps();

            $table->unique(['segment_id', 'workout_id']);
            // Inti leaderboard: urutkan waktu tercepat dalam sebuah segment.
            $table->index(['segment_id', 'elapsed_seconds']);
            // "Effort milik saya di segment ini".
            $table->index(['user_id', 'segment_id', 'elapsed_seconds']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_efforts');
    }
};
