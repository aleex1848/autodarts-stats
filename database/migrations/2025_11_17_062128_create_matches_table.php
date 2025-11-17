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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('autodarts_match_id')->unique();
            $table->string('variant')->default('X01');
            $table->string('type')->default('Online');

            // Settings
            $table->integer('base_score')->default(501);
            $table->string('in_mode')->default('Straight');
            $table->string('out_mode')->default('Straight');
            $table->string('bull_mode')->default('25/50');
            $table->integer('max_rounds')->default(20);

            // Status
            $table->foreignId('winner_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('autodarts_match_id');
            $table->index('finished_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
