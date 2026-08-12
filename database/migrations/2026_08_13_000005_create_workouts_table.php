<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->double('distance_meters')->nullable();
            $table->double('active_calories')->nullable();
            $table->double('average_heart_rate')->nullable();
            $table->double('max_heart_rate')->nullable();
            $table->double('average_pace_seconds_per_km')->nullable();
            $table->double('elevation_gain_meters')->nullable();
            $table->string('source');
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
