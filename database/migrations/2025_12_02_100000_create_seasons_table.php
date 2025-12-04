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
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('max_players')->default(20);
            $table->string('mode'); // single_round, double_round
            $table->string('variant'); // 501_single_single, 501_single_double
            $table->string('match_format'); // best_of_3, best_of_5
            $table->timestamp('registration_deadline')->nullable();
            $table->unsignedInteger('days_per_matchday')->default(7);
            $table->string('status')->default('registration'); // registration, active, completed, cancelled
            $table->string('image_path')->nullable();
            $table->foreignId('parent_season_id')->nullable()->constrained('seasons')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('slug');
            $table->index('status');
            $table->index('registration_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};

