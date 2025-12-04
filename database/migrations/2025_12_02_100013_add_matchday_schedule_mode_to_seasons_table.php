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
            $table->unsignedInteger('days_per_matchday')->nullable()->change();
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->string('matchday_schedule_mode')->default('timed')->after('days_per_matchday');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('matchday_schedule_mode');
            $table->unsignedInteger('days_per_matchday')->default(7)->nullable(false)->change();
        });
    }
};

