<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Graf sosial: siapa mengikuti siapa.
     */
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Satu pasangan hanya boleh punya satu baris.
            $table->unique(['follower_id', 'following_id']);
            // Feed membaca "siapa yang saya ikuti" secara terbalik.
            $table->index(['following_id', 'follower_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
