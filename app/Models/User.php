<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_identifying',
        'autodarts_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_identifying' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the player associated with this user
     */
    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    /**
     * Get all matches for this user through their player
     *
     * Usage: $user->matches()->get() or $user->matches
     */
    public function matches()
    {
        return DartMatch::whereHas('players', function ($query) {
            $query->where('players.user_id', $this->id);
        });
    }

    public function createdLeagues(): HasMany
    {
        return $this->hasMany(League::class, 'created_by_user_id');
    }

    public function leagueRegistrations(): HasMany
    {
        return $this->hasMany(LeagueRegistration::class);
    }

    /**
     * Get all leagues where the user is registered or is a participant
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, League>
     */
    public function leagues(): \Illuminate\Database\Eloquent\Collection
    {
        // Ensure player relationship is loaded
        if (!$this->relationLoaded('player')) {
            $this->load('player');
        }

        $leagueIds = collect();

        // Get league IDs from registrations by user_id
        $registeredByUserId = League::whereHas('registrations', function ($query) {
            $query->where('user_id', $this->id);
        })->pluck('id');

        $leagueIds = $leagueIds->merge($registeredByUserId);

        // Get league IDs from registrations by player_id (if player exists)
        if ($this->player) {
            $registeredByPlayerId = League::whereHas('registrations', function ($query) {
                $query->where('player_id', $this->player->id);
            })->pluck('id');

            $leagueIds = $leagueIds->merge($registeredByPlayerId);

            // Get league IDs from participants (through player)
            $participantLeagueIds = League::whereHas('participants', function ($query) {
                $query->where('player_id', $this->player->id);
            })->pluck('id');

            $leagueIds = $leagueIds->merge($participantLeagueIds);
        }

        // Get unique league IDs and fetch the leagues
        $uniqueLeagueIds = $leagueIds->unique()->values()->all();

        if (empty($uniqueLeagueIds)) {
            return League::query()->whereRaw('1 = 0')->get(); // Return empty Eloquent Collection
        }

        return League::whereIn('id', $uniqueLeagueIds)->get();
    }
}
