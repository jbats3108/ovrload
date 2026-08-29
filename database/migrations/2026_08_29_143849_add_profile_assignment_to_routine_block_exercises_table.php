<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('routine_block_exercises', function (Blueprint $table) {
            $table->foreignId('exercise_profile_id')
                ->nullable()
                ->after('routine_block_id')
                ->constrained('exercise_profiles')
                ->nullOnDelete();
            $table->char('exercise_profile_fingerprint', 64)->nullable()->after('exercise_profile_id');
            $table->boolean('floor_is_derived')->nullable()->after('achievement_floor_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routine_block_exercises', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exercise_profile_id');
            $table->dropColumn('exercise_profile_fingerprint');
            $table->dropColumn('floor_is_derived');
        });
    }
};
