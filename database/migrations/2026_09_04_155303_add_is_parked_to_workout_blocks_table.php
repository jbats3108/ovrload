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
        Schema::table('workout_blocks', function (Blueprint $table) {
            $table->boolean('is_parked')->default(false)->after('is_ad_hoc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_blocks', function (Blueprint $table) {
            $table->dropColumn('is_parked');
        });
    }
};
