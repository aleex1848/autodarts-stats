<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MatchPlayer extends Pivot
{
    /** @use HasFactory<\Database\Factories\MatchPlayerFactory> */
    use HasFactory;

    protected $table = 'match_player';

    protected $fillable = [
        'match_id',
        'player_id',
        'player_index',
        'legs_won',
        'sets_won',
        'final_position',
        'match_average',
        'checkout_rate',
        'total_180s',
    ];

    protected function casts(): array
    {
        return [
            'match_average' => 'decimal:2',
            'checkout_rate' => 'decimal:4',
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
