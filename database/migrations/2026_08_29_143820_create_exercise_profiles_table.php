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
        Schema::create('exercise_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 16);
            $table->string('status', 16);
            $table->string('name');
            $table->string('slug', 120)->nullable();
            $table->string('slug_scope', 80);
            $table->unsignedSmallInteger('target_reps');
            $table->unsignedSmallInteger('floor_override')->nullable();
            $table->unsignedInteger('working_rest_seconds');
            $table->json('warm_up_steps');
            $table->char('recipe_fingerprint', 64);
            $table->timestamp('published_at')->nullable();
            $table->index(['kind', 'status']);
            $table->index(['user_id', 'status']);
            $table->unique(['slug_scope', 'slug']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_profiles');
    }
};
