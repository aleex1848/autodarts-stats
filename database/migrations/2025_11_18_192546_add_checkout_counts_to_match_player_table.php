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
            $table->unsignedInteger('checkout_attempts')->nullable()->after('checkout_rate');
            $table->unsignedInteger('checkout_hits')->nullable()->after('checkout_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_player', function (Blueprint $table) {
            $table->dropColumn(['checkout_attempts', 'checkout_hits']);
        });
    }
};
