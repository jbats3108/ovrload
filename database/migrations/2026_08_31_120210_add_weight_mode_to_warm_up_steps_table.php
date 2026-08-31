<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_warm_up_steps', function (Blueprint $table) {
            $table->string('weight_mode', 16)->default('percent')->after('position');
            $table->unsignedTinyInteger('percent_of_working')->nullable()->change();
        });

        Schema::table('workout_warm_up_steps', function (Blueprint $table) {
            $table->string('weight_mode', 16)->default('percent')->after('position');
            $table->unsignedTinyInteger('percent_of_working')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('routine_warm_up_steps', function (Blueprint $table) {
            $table->dropColumn('weight_mode');
            $table->unsignedTinyInteger('percent_of_working')->nullable(false)->change();
        });

        Schema::table('workout_warm_up_steps', function (Blueprint $table) {
            $table->dropColumn('weight_mode');
            $table->unsignedTinyInteger('percent_of_working')->nullable(false)->change();
        });
    }
};
