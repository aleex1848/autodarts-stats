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
            $table->decimal('mpr', 6, 2)->nullable()->after('darts_thrown');
            $table->decimal('first_9_mpr', 6, 2)->nullable()->after('mpr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leg_player', function (Blueprint $table) {
            $table->dropColumn(['mpr', 'first_9_mpr']);
        });
    }
};

