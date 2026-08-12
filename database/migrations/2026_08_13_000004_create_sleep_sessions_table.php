<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sleep_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('bedtime');
            $table->dateTime('wake_time');
            $table->double('rem_minutes')->default(0);
            $table->double('deep_minutes')->default(0);
            $table->double('core_minutes')->default(0);
            $table->double('awake_minutes')->default(0);
            $table->unsignedTinyInteger('sleep_score')->nullable();
            $table->string('source');
            $table->timestamps();

            $table->index(['user_id', 'bedtime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sleep_sessions');
    }
};
