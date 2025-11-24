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
        if (! Schema::hasTable('bull_offs')) {
            Schema::create('bull_offs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('match_id')->constrained()->cascadeOnDelete();
                $table->foreignId('player_id')->constrained()->cascadeOnDelete();
                $table->uuid('autodarts_turn_id')->unique();
                $table->integer('score')->comment('Negative score indicating distance from bull');
                $table->timestamp('thrown_at');
                $table->timestamps();

                $table->index('match_id');
                $table->index('player_id');
            });
        } else {
            // Table already exists, just add missing columns if needed
            Schema::table('bull_offs', function (Blueprint $table) {
                if (! Schema::hasColumn('bull_offs', 'match_id')) {
                    $table->foreignId('match_id')->constrained()->cascadeOnDelete();
                }
                if (! Schema::hasColumn('bull_offs', 'player_id')) {
                    $table->foreignId('player_id')->constrained()->cascadeOnDelete();
                }
                if (! Schema::hasColumn('bull_offs', 'autodarts_turn_id')) {
                    $table->uuid('autodarts_turn_id')->unique();
                }
                if (! Schema::hasColumn('bull_offs', 'score')) {
                    $table->integer('score')->comment('Negative score indicating distance from bull');
                }
                if (! Schema::hasColumn('bull_offs', 'thrown_at')) {
                    $table->timestamp('thrown_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bull_offs');
    }
};
