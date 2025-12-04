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
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['platform', 'league'])->default('league');
            $table->string('title');
            $table->string('slug');
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('news_categories')->onDelete('set null');
            $table->foreignId('league_id')->nullable()->constrained('leagues')->onDelete('cascade');
            $table->foreignId('season_id')->nullable()->constrained('seasons')->onDelete('cascade');
            $table->foreignId('matchday_id')->nullable()->constrained('matchdays')->onDelete('set null');
            $table->foreignId('matchday_fixture_id')->nullable()->constrained('matchday_fixtures')->onDelete('set null');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('category_id');
            $table->index('league_id');
            $table->index('season_id');
            $table->index('matchday_id');
            $table->index('matchday_fixture_id');
            $table->index('published_at');
            $table->index('is_published');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};

