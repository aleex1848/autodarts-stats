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
        Schema::table('match_player', function (Blueprint $table) {
            $table->unsignedInteger('darts_thrown')->nullable()->after('total_180s');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_player', function (Blueprint $table) {
            $table->dropColumn('darts_thrown');
        });
    }
};
