<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_block_exercises', function (Blueprint $table) {
            $table->foreignId('deload_exercise_id')
                ->nullable()
                ->after('working_weight_g')
                ->constrained('exercises')
                ->restrictOnDelete();
            $table->unsignedInteger('deload_working_weight_g')
                ->nullable()
                ->after('deload_exercise_id');
        });
    }

    public function down(): void
    {
        Schema::table('routine_block_exercises', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deload_exercise_id');
            $table->dropColumn('deload_working_weight_g');
        });
    }
};
