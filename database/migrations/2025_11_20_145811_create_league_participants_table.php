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
        Schema::create('league_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('matches_won')->default(0);
            $table->unsignedInteger('matches_lost')->default(0);
            $table->unsignedInteger('matches_draw')->default(0);
            $table->unsignedInteger('legs_won')->default(0);
            $table->unsignedInteger('legs_lost')->default(0);
            $table->integer('penalty_points')->default(0);
            $table->unsignedInteger('final_position')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'player_id']);
            $table->index(['league_id', 'points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_participants');
    }
};
