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
        Schema::create('throws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained()->cascadeOnDelete();
            $table->uuid('autodarts_throw_id');
            $table->foreignId('webhook_call_id')->nullable()->constrained('webhook_calls')->nullOnDelete();
            
            // Throw data
            $table->unsignedTinyInteger('dart_number');
            $table->unsignedTinyInteger('segment_number')->nullable();
            $table->unsignedTinyInteger('multiplier')->default(1);
            $table->unsignedInteger('points')->default(0);
            $table->string('segment_name')->nullable();
            $table->string('segment_bed')->nullable();
            
            // Coordinates
            $table->decimal('coords_x', 10, 8)->nullable();
            $table->decimal('coords_y', 10, 8)->nullable();
            
            // Correction tracking
            $table->boolean('is_corrected')->default(false);
            $table->timestamp('corrected_at')->nullable();
            $table->foreignId('corrected_by_throw_id')->nullable()->constrained('throws')->nullOnDelete();
            
            $table->timestamps();

            $table->index(['turn_id', 'dart_number']);
            $table->index('is_corrected');
            $table->index('webhook_call_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('throws');
    }
};
