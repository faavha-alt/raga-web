<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->double('acute_load')->nullable();
            $table->double('chronic_load')->nullable();
            $table->double('acute_chronic_ratio')->nullable();
            $table->double('weekly_distance_meters')->nullable();
            $table->double('weekly_duration_minutes')->nullable();
            $table->unsignedTinyInteger('training_frequency')->nullable();
            $table->double('monotony')->nullable();
            $table->string('risk_level')->default('optimal');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_loads');
    }
};
