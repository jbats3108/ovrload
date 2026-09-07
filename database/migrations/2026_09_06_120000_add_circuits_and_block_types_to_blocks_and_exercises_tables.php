<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_blocks', function (Blueprint $table) {
            $table->string('type', 16)->default('single')->after('position');
            $table->unsignedSmallInteger('stage_rest_seconds')->nullable()->after('type');
        });

        Schema::table('workout_blocks', function (Blueprint $table) {
            $table->string('type', 16)->default('single')->after('position');
            $table->unsignedSmallInteger('stage_rest_seconds')->nullable()->after('type');
        });

        DB::table('routine_blocks')->where('is_superset', true)->update(['type' => 'superset']);
        DB::table('workout_blocks')->where('is_superset', true)->update(['type' => 'superset']);

        Schema::table('routine_block_exercises', function (Blueprint $table) {
            $table->string('prescription_mode', 16)->default('reps')->after('position');
            $table->unsignedSmallInteger('prescribed_duration_seconds')->nullable()->after('prescription_mode');
            $table->unsignedSmallInteger('prescribed_reps')->nullable()->change();
        });

        Schema::table('workout_block_exercises', function (Blueprint $table) {
            $table->string('prescription_mode', 16)->default('reps')->after('position');
            $table->unsignedSmallInteger('prescribed_duration_seconds')->nullable()->after('prescription_mode');
            $table->unsignedSmallInteger('prescribed_reps')->nullable()->change();
        });

        Schema::table('workout_sets', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_seconds')->nullable()->after('reps');
            $table->boolean('is_skipped')->default(false)->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->dropColumn(['duration_seconds', 'is_skipped']);
        });

        DB::table('workout_block_exercises')->whereNull('prescribed_reps')->update(['prescribed_reps' => 1]);
        Schema::table('workout_block_exercises', function (Blueprint $table) {
            $table->unsignedSmallInteger('prescribed_reps')->nullable(false)->change();
            $table->dropColumn(['prescription_mode', 'prescribed_duration_seconds']);
        });

        DB::table('routine_block_exercises')->whereNull('prescribed_reps')->update(['prescribed_reps' => 1]);
        Schema::table('routine_block_exercises', function (Blueprint $table) {
            $table->unsignedSmallInteger('prescribed_reps')->nullable(false)->change();
            $table->dropColumn(['prescription_mode', 'prescribed_duration_seconds']);
        });

        Schema::table('workout_blocks', function (Blueprint $table) {
            $table->dropColumn(['type', 'stage_rest_seconds']);
        });

        Schema::table('routine_blocks', function (Blueprint $table) {
            $table->dropColumn(['type', 'stage_rest_seconds']);
        });
    }
};
