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
        Schema::create('match_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('player_index')->default(0);
            
            // Stats
            $table->unsignedInteger('legs_won')->default(0);
            $table->unsignedInteger('sets_won')->default(0);
            $table->unsignedTinyInteger('final_position')->nullable();
            $table->decimal('match_average', 6, 2)->nullable();
            $table->decimal('checkout_rate', 5, 4)->nullable();
            $table->unsignedInteger('total_180s')->default(0);
            
            $table->timestamps();

            $table->unique(['match_id', 'player_id']);
            $table->index(['match_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_player');
    }
};
