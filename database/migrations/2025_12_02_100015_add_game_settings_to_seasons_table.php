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
        Schema::table('seasons', function (Blueprint $table) {
            $table->unsignedInteger('base_score')->nullable()->after('match_format');
            $table->string('in_mode')->nullable()->after('base_score');
            $table->string('out_mode')->nullable()->after('in_mode');
            $table->string('bull_mode')->nullable()->after('out_mode');
            $table->unsignedInteger('max_rounds')->nullable()->after('bull_mode');
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
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn([
                'base_score',
                'in_mode',
                'out_mode',
                'bull_mode',
                'max_rounds',
                'bull_off',
                'match_mode_type',
                'match_mode_legs_count',
                'match_mode_sets_count',
            ]);
        });
    }
};
