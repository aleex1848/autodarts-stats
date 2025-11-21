<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'player_id',
        'points',
        'matches_played',
        'matches_won',
        'matches_lost',
        'matches_draw',
        'legs_won',
        'legs_lost',
        'penalty_points',
        'final_position',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'matches_played' => 'integer',
            'matches_won' => 'integer',
            'matches_lost' => 'integer',
            'matches_draw' => 'integer',
            'legs_won' => 'integer',
            'legs_lost' => 'integer',
            'penalty_points' => 'integer',
            'final_position' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
