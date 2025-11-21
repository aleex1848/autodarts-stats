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
        Schema::create('matchday_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matchday_id')->constrained('matchdays')->onDelete('cascade');
            $table->foreignId('home_player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('away_player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('dart_match_id')->nullable()->constrained('matches')->onDelete('set null');
            $table->string('status')->default('scheduled'); // scheduled, completed, overdue, walkover
            $table->unsignedInteger('home_legs_won')->nullable();
            $table->unsignedInteger('away_legs_won')->nullable();
            $table->foreignId('winner_player_id')->nullable()->constrained('players')->onDelete('set null');
            $table->unsignedInteger('points_awarded_home')->default(0);
            $table->unsignedInteger('points_awarded_away')->default(0);
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->index(['matchday_id', 'status']);
            $table->index('dart_match_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matchday_fixtures');
    }
};
