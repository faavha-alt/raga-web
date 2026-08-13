<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recovery_scores', function (Blueprint $table) {
            $table->double('stress_contribution')->nullable()->after('sleep_contribution');
        });
    }

    public function down(): void
    {
        Schema::table('recovery_scores', function (Blueprint $table) {
            $table->dropColumn('stress_contribution');
        });
    }
};
