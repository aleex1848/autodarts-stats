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
            // Change points from unsignedInteger to integer to allow negative values (e.g., Bull-Out misses)
            $table->integer('points')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            // Revert back to unsignedInteger (this will fail if negative values exist)
            $table->unsignedInteger('points')->default(0)->change();
        });
    }
};
