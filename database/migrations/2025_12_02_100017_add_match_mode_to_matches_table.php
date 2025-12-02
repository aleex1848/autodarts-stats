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
        Schema::table('matches', function (Blueprint $table) {
            $table->string('bull_off')->nullable()->after('max_rounds');
            $table->string('match_mode_type')->nullable()->after('bull_off');
            $table->unsignedInteger('match_mode_legs_count')->nullable()->after('match_mode_type');
            $table->unsignedInteger('match_mode_sets_count')->nullable()->after('match_mode_legs_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn([
                'bull_off',
                'match_mode_type',
                'match_mode_legs_count',
                'match_mode_sets_count',
            ]);
        });
    }
};
