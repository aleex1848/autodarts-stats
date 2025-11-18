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
        Schema::create('leg_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leg_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->decimal('average', 6, 2)->nullable();
            $table->decimal('checkout_rate', 5, 4)->nullable();
            $table->unsignedInteger('darts_thrown')->nullable();
            $table->unsignedInteger('checkout_attempts')->nullable();
            $table->unsignedInteger('checkout_hits')->nullable();
            $table->timestamps();

            $table->unique(['leg_id', 'player_id']);
            $table->index(['leg_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leg_player');
    }
};
