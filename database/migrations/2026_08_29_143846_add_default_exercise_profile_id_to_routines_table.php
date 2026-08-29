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
        Schema::table('routines', function (Blueprint $table) {
            $table->foreignId('default_exercise_profile_id')
                ->nullable()
                ->after('deload_every_n')
                ->constrained('exercise_profiles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_exercise_profile_id');
        });
    }
};
