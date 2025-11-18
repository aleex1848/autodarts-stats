<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Leg extends Model
{
    /** @use HasFactory<\Database\Factories\LegFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'leg_number',
        'set_number',
        'winner_player_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(DartMatch::class, 'match_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }

    public function legPlayers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'leg_player', 'leg_id', 'player_id')
            ->withPivot([
                'average',
                'checkout_rate',
                'darts_thrown',
                'checkout_attempts',
                'checkout_hits',
            ])
            ->withTimestamps();
    }
}
