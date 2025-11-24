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
        // Only add foreign keys for MySQL/MariaDB, not for SQLite
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('bull_offs', function (Blueprint $table) {
                // Check if foreign keys already exist before adding them
                if (! $this->hasForeignKey('bull_offs', 'bull_offs_match_id_foreign')) {
                    $table->foreign('match_id')->references('id')->on('matches')->onDelete('cascade');
                }
                if (! $this->hasForeignKey('bull_offs', 'bull_offs_player_id_foreign')) {
                    $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
                }
            });

            // Add indexes separately
            Schema::table('bull_offs', function (Blueprint $table) {
                if (! $this->hasIndex('bull_offs', 'bull_offs_match_id_index')) {
                    $table->index('match_id', 'bull_offs_match_id_index');
                }
                if (! $this->hasIndex('bull_offs', 'bull_offs_player_id_index')) {
                    $table->index('player_id', 'bull_offs_player_id_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('bull_offs', function (Blueprint $table) {
                try {
                    $table->dropForeign(['match_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                try {
                    $table->dropForeign(['player_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                try {
                    $table->dropIndex('bull_offs_match_id_index');
                } catch (\Exception $e) {
                    // Index might not exist
                }
                try {
                    $table->dropIndex('bull_offs_player_id_index');
                } catch (\Exception $e) {
                    // Index might not exist
                }
            });
        }
    }

    protected function hasIndex(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $databaseName = $connection->getDatabaseName();

            $result = $connection->select(
                'SELECT INDEX_NAME 
                 FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = ? 
                 AND TABLE_NAME = ? 
                 AND INDEX_NAME = ?',
                [$databaseName, $table, $index]
            );

            return ! empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function hasForeignKey(string $table, string $foreignKey): bool
    {
        try {
            $connection = Schema::getConnection();
            $databaseName = $connection->getDatabaseName();

            $result = $connection->select(
                'SELECT CONSTRAINT_NAME 
                 FROM information_schema.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = ? 
                 AND TABLE_NAME = ? 
                 AND CONSTRAINT_NAME = ? 
                 AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$databaseName, $table, $foreignKey]
            );

            return ! empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
};
