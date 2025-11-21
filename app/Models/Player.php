<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Player extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerFactory> */
    use HasFactory;

    protected $fillable = [
        'autodarts_user_id',
        'name',
        'email',
        'country',
        'avatar_url',
        'user_id',
    ];

    public function matches(): BelongsToMany
    {
        return $this->belongsToMany(DartMatch::class, 'match_player', 'player_id', 'match_id')
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

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }

    public function throws(): HasManyThrough
    {
        return $this->hasManyThrough(DartThrow::class, Turn::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leagueRegistrations(): HasMany
    {
        return $this->hasMany(LeagueRegistration::class);
    }

    public function leagueParticipants(): HasMany
    {
        return $this->hasMany(LeagueParticipant::class);
    }
}
