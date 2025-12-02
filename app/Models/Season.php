<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
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
        'image_path',
        'parent_season_id',
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

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function parentSeason(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'parent_season_id');
    }

    public function subSeasons(): HasMany
    {
        return $this->hasMany(Season::class, 'parent_season_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(SeasonRegistration::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SeasonParticipant::class);
    }

    public function matchdays(): HasMany
    {
        return $this->hasMany(Matchday::class);
    }

    public function coAdmins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'season_co_admins')
            ->withTimestamps();
    }

    public function isAdmin(User $user): bool
    {
        return $this->created_by_user_id === $user->id
            || $this->coAdmins()->where('user_id', $user->id)->exists();
    }
}
