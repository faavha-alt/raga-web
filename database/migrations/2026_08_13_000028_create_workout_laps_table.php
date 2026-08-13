<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_laps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('lap_index');
            $table->dateTime('start_time');
            $table->double('distance_meters')->nullable();
            $table->double('duration_seconds')->nullable();
            $table->double('elevation_gain_meters')->nullable();
            $table->double('elevation_loss_meters')->nullable();
            $table->double('average_heart_rate')->nullable();
            $table->double('max_heart_rate')->nullable();
            $table->double('average_pace_seconds_per_km')->nullable();
            $table->double('calories')->nullable();
            $table->timestamps();

            $table->index(['workout_id', 'lap_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_laps');
    }
};
