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
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('max_players')->default(20);
            $table->string('mode'); // single_round, double_round
            $table->string('variant'); // 501_single_single, 501_single_double
            $table->string('match_format'); // best_of_3, best_of_5
            $table->timestamp('registration_deadline')->nullable();
            $table->unsignedInteger('days_per_matchday')->default(7);
            $table->string('status')->default('registration'); // registration, active, completed, cancelled
            $table->foreignId('parent_league_id')->nullable()->constrained('leagues')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('status');
            $table->index('registration_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
