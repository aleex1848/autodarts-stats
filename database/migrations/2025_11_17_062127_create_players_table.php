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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->uuid('autodarts_user_id')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamps();

            $table->index('autodarts_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
