<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turn extends Model
{
    /** @use HasFactory<\Database\Factories\TurnFactory> */
    use HasFactory;

    protected $fillable = [
        'leg_id',
        'player_id',
        'autodarts_turn_id',
        'round_number',
        'turn_number',
        'points',
        'score_after',
        'busted',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'busted' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function leg(): BelongsTo
    {
        return $this->belongsTo(Leg::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function throws(): HasMany
    {
        return $this->hasMany(DartThrow::class, 'turn_id');
    }
}
