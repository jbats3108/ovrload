<?php

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
    }

    /**
     * The column belongs to the original profile assignment migration on fresh installs.
     */
    public function down(): void {}
};
