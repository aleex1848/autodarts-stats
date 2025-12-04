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
        Schema::create('openai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('model')->default('o1-preview');
            $table->timestamps();
        });

        // Insert default setting
        \Illuminate\Support\Facades\DB::table('openai_settings')->insert([
            'model' => 'o1-preview',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('openai_settings');
    }
};

