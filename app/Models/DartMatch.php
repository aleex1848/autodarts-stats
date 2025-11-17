<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DartMatch extends Model
{
    /** @use HasFactory<\Database\Factories\DartMatchFactory> */
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'autodarts_match_id',
        'variant',
        'type',
        'base_score',
        'in_mode',
        'out_mode',
        'bull_mode',
        'max_rounds',
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

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'match_player', 'match_id', 'player_id')
            ->using(MatchPlayer::class)
            ->withPivot([
                'player_index',
                'legs_won',
                'sets_won',
                'final_position',
                'match_average',
                'checkout_rate',
                'total_180s',
            ])
            ->withTimestamps();
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(Leg::class, 'match_id');
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function scopeFinished(Builder $query): void
    {
        $query->whereNotNull('finished_at');
    }

    public function scopeOngoing(Builder $query): void
    {
        $query->whereNull('finished_at');
    }
}
