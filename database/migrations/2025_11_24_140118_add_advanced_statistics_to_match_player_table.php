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
            $table->decimal('average_until_170', 6, 2)->nullable()->after('match_average');
            $table->decimal('first_9_average', 6, 2)->nullable()->after('average_until_170');
            $table->unsignedInteger('best_checkout_points')->nullable()->after('checkout_hits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_player', function (Blueprint $table) {
            $table->dropColumn(['average_until_170', 'first_9_average', 'best_checkout_points']);
        });
    }
};
