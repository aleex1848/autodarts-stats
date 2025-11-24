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
        Schema::table('turns', function (Blueprint $table) {
            // Change score_after from unsignedInteger to integer (signed) to allow negative values
            // This is needed for Bull-Out situations where the score can be negative
            $table->integer('score_after')->nullable()->change();

            // Also change points to signed integer to allow negative values
            $table->integer('points')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            // Revert back to unsignedInteger (note: this might fail if negative values exist)
            $table->unsignedInteger('score_after')->nullable()->change();
            $table->unsignedInteger('points')->default(0)->change();
        });
    }
};
