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
        Schema::create('matchdays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->unsignedInteger('matchday_number');
            $table->boolean('is_return_round')->default(false);
            $table->timestamp('deadline_at')->nullable();
            $table->boolean('is_playoff')->default(false);
            $table->timestamps();

            $table->index(['league_id', 'matchday_number']);
            $table->index('deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matchdays');
    }
};
