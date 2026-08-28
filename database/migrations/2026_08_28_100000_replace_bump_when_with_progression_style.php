<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('progression_style_default', 32)->default('straight_sets')->after('progression_target_default');
            $table->string('progressive_mid_block_default', 32)->default('ask')->after('progression_style_default');
        });

        Schema::table('workouts', function (Blueprint $table): void {
            $table->string('progression_style', 32)->default('straight_sets')->after('mode');
            $table->string('progressive_mid_block', 32)->default('ask')->after('progression_style');
        });

        DB::table('users')->where('bump_when_default', 'any_set')->update([
            'progression_style_default' => 'straight_sets',
            'progressive_mid_block_default' => 'ask',
        ]);

        DB::table('users')->where('bump_when_default', 'last_at_top_weight')->update([
            'progression_style_default' => 'progressive_overload',
            'progressive_mid_block_default' => 'ask',
        ]);

        DB::table('workouts')->where('bump_when', 'any_set')->update([
            'progression_style' => 'straight_sets',
            'progressive_mid_block' => 'ask',
        ]);

        DB::table('workouts')->where('bump_when', 'last_at_top_weight')->update([
            'progression_style' => 'progressive_overload',
            'progressive_mid_block' => 'ask',
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('bump_when_default');
        });

        Schema::table('workouts', function (Blueprint $table): void {
            $table->dropColumn('bump_when');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('bump_when_default', 32)->default('any_set')->after('progression_target_default');
        });

        Schema::table('workouts', function (Blueprint $table): void {
            $table->string('bump_when', 32)->default('any_set')->after('mode');
        });

        DB::table('users')->where('progression_style_default', 'straight_sets')->update([
            'bump_when_default' => 'any_set',
        ]);

        DB::table('users')->where('progression_style_default', 'progressive_overload')->update([
            'bump_when_default' => 'last_at_top_weight',
        ]);

        DB::table('workouts')->where('progression_style', 'straight_sets')->update([
            'bump_when' => 'any_set',
        ]);

        DB::table('workouts')->where('progression_style', 'progressive_overload')->update([
            'bump_when' => 'last_at_top_weight',
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['progression_style_default', 'progressive_mid_block_default']);
        });

        Schema::table('workouts', function (Blueprint $table): void {
            $table->dropColumn(['progression_style', 'progressive_mid_block']);
        });
    }
};
