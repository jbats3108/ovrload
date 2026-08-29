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
        Schema::table('routine_blocks', function (Blueprint $table) {
            $table->foreignId('shared_exercise_profile_id')
                ->nullable()
                ->after('routine_id')
                ->constrained('exercise_profiles')
                ->nullOnDelete();
            $table->char('shared_profile_fingerprint', 64)->nullable()->after('shared_exercise_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routine_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shared_exercise_profile_id');
            $table->dropColumn('shared_profile_fingerprint');
        });
    }
};
