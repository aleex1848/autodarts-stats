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
        Schema::table('leg_player', function (Blueprint $table) {
            $table->unsignedInteger('busted_count')->nullable()->after('checkout_hits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leg_player', function (Blueprint $table) {
            $table->dropColumn('busted_count');
        });
    }
};
