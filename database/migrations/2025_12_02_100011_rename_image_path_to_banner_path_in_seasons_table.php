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
        // MySQL unterstützt renameColumn nicht direkt, daher müssen wir es anders machen
        Schema::table('seasons', function (Blueprint $table) {
            $table->string('banner_path')->nullable()->after('status');
        });

        // Migriere Daten
        DB::table('seasons')->update([
            'banner_path' => DB::raw('image_path'),
        ]);

        // Lösche alte Spalte
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('status');
        });

        // Migriere Daten zurück
        DB::table('seasons')->update([
            'image_path' => DB::raw('banner_path'),
        ]);

        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('banner_path');
        });
    }
};
