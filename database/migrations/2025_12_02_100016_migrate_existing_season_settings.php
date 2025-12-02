<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate variant to base_score, in_mode, out_mode
        DB::table('seasons')
            ->where('variant', '501_single_single')
            ->update([
                'base_score' => 501,
                'in_mode' => 'Straight',
                'out_mode' => 'Straight',
            ]);

        DB::table('seasons')
            ->where('variant', '501_single_double')
            ->update([
                'base_score' => 501,
                'in_mode' => 'Straight',
                'out_mode' => 'Double',
            ]);

        // Migrate match_format to match_mode_type and match_mode_legs_count
        DB::table('seasons')
            ->where('match_format', 'best_of_3')
            ->update([
                'match_mode_type' => 'Legs',
                'match_mode_legs_count' => 2,
            ]);

        DB::table('seasons')
            ->where('match_format', 'best_of_5')
            ->update([
                'match_mode_type' => 'Legs',
                'match_mode_legs_count' => 3,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we cannot determine
        // which variant/match_format was originally set
    }
};
