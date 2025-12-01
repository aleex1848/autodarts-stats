<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_players',
        'mode',
        'variant',
        'match_format',
        'registration_deadline',
        'days_per_matchday',
        'status',
        'parent_league_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'registration_deadline' => 'datetime',
            'max_players' => 'integer',
            'days_per_matchday' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function parentLeague(): BelongsTo
    {
        return $this->belongsTo(League::class, 'parent_league_id');
    }

    public function subLeagues(): HasMany
    {
        return $this->hasMany(League::class, 'parent_league_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(LeagueRegistration::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LeagueParticipant::class);
    }

    public function matchdays(): HasMany
    {
        return $this->hasMany(Matchday::class);
    }
}
