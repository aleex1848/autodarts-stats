<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchdayFixture extends Model
{
    use HasFactory;

    protected $fillable = [
        'matchday_id',
        'home_player_id',
        'away_player_id',
        'dart_match_id',
        'status',
        'home_legs_won',
        'away_legs_won',
        'winner_player_id',
        'points_awarded_home',
        'points_awarded_away',
        'played_at',
    ];

    protected function casts(): array
    {
        return [
            'home_legs_won' => 'integer',
            'away_legs_won' => 'integer',
            'points_awarded_home' => 'integer',
            'points_awarded_away' => 'integer',
            'played_at' => 'datetime',
        ];
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    public function homePlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'home_player_id');
    }

    public function awayPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'away_player_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    public function dartMatch(): BelongsTo
    {
        return $this->belongsTo(DartMatch::class, 'dart_match_id');
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'matchday_fixture_id');
    }

    /**
     * Get the first published news for this fixture.
     */
    public function getFirstNewsAttribute(): ?News
    {
        return $this->news()->where('is_published', true)->first();
    }
}
