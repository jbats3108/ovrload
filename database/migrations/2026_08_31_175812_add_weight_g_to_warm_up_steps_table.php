<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_warm_up_steps', function (Blueprint $table) {
            $table->unsignedInteger('weight_g')->nullable()->after('percent_of_working');
        });

        Schema::table('workout_warm_up_steps', function (Blueprint $table) {
            $table->unsignedInteger('weight_g')->nullable()->after('percent_of_working');
        });
    }

    public function down(): void
    {
        Schema::table('workout_warm_up_steps', function (Blueprint $table) {
            $table->dropColumn('weight_g');
        });

        Schema::table('routine_warm_up_steps', function (Blueprint $table) {
            $table->dropColumn('weight_g');
        });
    }
};
