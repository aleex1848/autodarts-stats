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
        Schema::table('openai_settings', function (Blueprint $table) {
            $table->unsignedInteger('max_tokens')->default(2000)->after('model');
            $table->unsignedInteger('max_completion_tokens')->default(4000)->after('max_tokens');
        });

        // Update existing record with defaults
        DB::table('openai_settings')->update([
            'max_tokens' => 2000,
            'max_completion_tokens' => 4000,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('openai_settings', function (Blueprint $table) {
            $table->dropColumn(['max_tokens', 'max_completion_tokens']);
        });
    }
};

