<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->string('name')->nullable()->after('type');
            $table->double('elevation_loss_meters')->nullable()->after('elevation_gain_meters');
            $table->double('training_effect_aerobic')->nullable()->after('elevation_loss_meters');
            $table->double('training_effect_anaerobic')->nullable()->after('training_effect_aerobic');
            $table->string('training_effect_label')->nullable()->after('training_effect_anaerobic');
            $table->double('training_load')->nullable()->after('training_effect_label');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'elevation_loss_meters',
                'training_effect_aerobic',
                'training_effect_anaerobic',
                'training_effect_label',
                'training_load',
            ]);
        });
    }
};
