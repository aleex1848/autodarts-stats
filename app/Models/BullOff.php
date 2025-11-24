<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BullOff extends Model
{
    /** @use HasFactory<\Database\Factories\BullOffFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'player_id',
        'autodarts_turn_id',
        'score',
        'thrown_at',
    ];

    protected function casts(): array
    {
        return [
            'thrown_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(DartMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
