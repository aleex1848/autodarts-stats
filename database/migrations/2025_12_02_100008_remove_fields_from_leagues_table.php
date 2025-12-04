<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Entferne Foreign Key Constraint für parent_league_id ZUERST
        // MySQL erfordert, dass wir den genauen Namen des Foreign Keys kennen
        if (Schema::hasColumn('leagues', 'parent_league_id')) {
            try {
                // Versuche zuerst den Foreign Key durch Abfrage zu finden
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'leagues' 
                    AND COLUMN_NAME = 'parent_league_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                if (!empty($foreignKeys)) {
                    foreach ($foreignKeys as $fk) {
                        DB::statement("ALTER TABLE leagues DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                    }
                } else {
                    // Fallback: Versuche den Standard-Namen
                    DB::statement("ALTER TABLE leagues DROP FOREIGN KEY `leagues_parent_league_id_foreign`");
                }
            } catch (\Exception $e) {
                // Versuche den Standard-Namen, falls die Abfrage fehlschlägt
                try {
                    DB::statement("ALTER TABLE leagues DROP FOREIGN KEY `leagues_parent_league_id_foreign`");
                } catch (\Exception $e2) {
                    // Foreign Key existiert möglicherweise nicht, ignoriere Fehler
                }
            }
        }

        // Entferne Indizes
        Schema::table('leagues', function (Blueprint $table) {
            if (Schema::hasColumn('leagues', 'status')) {
                try {
                    $table->dropIndex(['status']);
                } catch (\Exception $e) {
                    // Index existiert möglicherweise nicht mehr
                }
            }
            if (Schema::hasColumn('leagues', 'registration_deadline')) {
                try {
                    $table->dropIndex(['registration_deadline']);
                } catch (\Exception $e) {
                    // Index existiert möglicherweise nicht mehr
                }
            }
        });

        // Entferne Spalten OHNE Foreign Keys zuerst
        Schema::table('leagues', function (Blueprint $table) {
            $columnsToDrop = [];
            
            if (Schema::hasColumn('leagues', 'max_players')) {
                $columnsToDrop[] = 'max_players';
            }
            if (Schema::hasColumn('leagues', 'mode')) {
                $columnsToDrop[] = 'mode';
            }
            if (Schema::hasColumn('leagues', 'variant')) {
                $columnsToDrop[] = 'variant';
            }
            if (Schema::hasColumn('leagues', 'match_format')) {
                $columnsToDrop[] = 'match_format';
            }
            if (Schema::hasColumn('leagues', 'registration_deadline')) {
                $columnsToDrop[] = 'registration_deadline';
            }
            if (Schema::hasColumn('leagues', 'days_per_matchday')) {
                $columnsToDrop[] = 'days_per_matchday';
            }
            if (Schema::hasColumn('leagues', 'status')) {
                $columnsToDrop[] = 'status';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        // Entferne parent_league_id separat (nachdem Foreign Key bereits gelöscht wurde)
        Schema::table('leagues', function (Blueprint $table) {
            if (Schema::hasColumn('leagues', 'parent_league_id')) {
                $table->dropColumn('parent_league_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            // Füge Felder wieder hinzu
            $table->unsignedInteger('max_players')->default(20)->after('description');
            $table->string('mode')->after('max_players');
            $table->string('variant')->after('mode');
            $table->string('match_format')->after('variant');
            $table->timestamp('registration_deadline')->nullable()->after('match_format');
            $table->unsignedInteger('days_per_matchday')->default(7)->after('registration_deadline');
            $table->string('status')->default('registration')->after('days_per_matchday');
            $table->foreignId('parent_league_id')->nullable()->constrained('leagues')->onDelete('cascade')->after('status');

            // Füge Indizes wieder hinzu
            $table->index('status');
            $table->index('registration_deadline');
        });
    }
};

