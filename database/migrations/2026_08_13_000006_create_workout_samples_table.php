<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->dateTime('timestamp');
            $table->double('heart_rate')->nullable();
            $table->double('pace_seconds_per_km')->nullable();
            $table->double('altitude_meters')->nullable();
            $table->double('cadence')->nullable();
            $table->timestamps();

            $table->index(['workout_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_samples');
    }
};
