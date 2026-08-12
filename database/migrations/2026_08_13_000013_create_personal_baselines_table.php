<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('metric_type');
            $table->unsignedSmallInteger('window_days');
            $table->double('mean_value');
            $table->double('standard_deviation');
            $table->unsignedInteger('sample_count');
            $table->dateTime('calculated_at');
            $table->timestamps();

            $table->index(['user_id', 'metric_type', 'window_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_baselines');
    }
};
