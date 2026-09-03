<?php

use Database\Seeders\ExerciseProfileSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('routine_block_exercises', 'floor_is_derived')) {
            Schema::table('routine_block_exercises', function (Blueprint $table): void {
                $table->boolean('floor_is_derived')->nullable()->after('achievement_floor_override');
            });
        }

        // Published OVRLOAD presets for fresh migrate / tests. Legacy user→profile
        // conversion lived in ExerciseProfileBackfillService and has been retired.
        (new ExerciseProfileSeeder)->run();
    }

    /**
     * Profile assignments are user data and are intentionally retained on rollback.
     */
    public function down(): void {}
};
