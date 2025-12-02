<?php

namespace App\Models;

use App\Enums\MatchdayScheduleMode;
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
        'matchday_schedule_mode',
        'status',
        'banner_path',
        'logo_path',
        'parent_season_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'registration_deadline' => 'datetime',
            'max_players' => 'integer',
            'days_per_matchday' => 'integer',
            'matchday_schedule_mode' => MatchdayScheduleMode::class,
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class)->withDefault();
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

    /**
     * Get the banner path with fallback to league banner
     */
    public function getBannerPath(): ?string
    {
        return $this->attributes['banner_path'] ?? $this->league?->banner_path;
    }

    /**
     * Get the logo path with fallback to league logo
     */
    public function getLogoPath(): ?string
    {
        return $this->attributes['logo_path'] ?? $this->league?->logo_path;
    }

    /**
     * Check if season has its own banner (not using league fallback)
     */
    public function hasOwnBanner(): bool
    {
        return $this->attributes['banner_path'] !== null;
    }

    /**
     * Check if season has its own logo (not using league fallback)
     */
    public function hasOwnLogo(): bool
    {
        return $this->attributes['logo_path'] !== null;
    }

    /**
     * Check if this season uses timed schedule mode.
     */
    public function isTimedSchedule(): bool
    {
        return $this->matchday_schedule_mode === MatchdayScheduleMode::Timed;
    }

    /**
     * Check if this season requires matchdays to be completed in order.
     */
    public function requiresMatchdayOrder(): bool
    {
        return $this->matchday_schedule_mode === MatchdayScheduleMode::UnlimitedWithOrder;
    }

    /**
     * Get the next relevant matchday for a user.
     * Only for non-completed seasons, returns the first matchday that is upcoming or currently active.
     * User must be a participant of the season.
     */
    public function getNextRelevantMatchday(User $user): ?Matchday
    {
        // Only for non-completed seasons
        if ($this->status === 'completed' || $this->status === 'cancelled') {
            return null;
        }

        // Check if user is a participant
        if (! $user->player) {
            return null;
        }

        $isParticipant = $this->participants()
            ->where('player_id', $user->player->id)
            ->exists();

        if (! $isParticipant) {
            return null;
        }

        // Get all matchdays ordered by matchday_number
        $matchdays = $this->matchdays()
            ->orderBy('matchday_number')
            ->orderBy('is_return_round')
            ->get();

        // Find the first matchday that is upcoming or currently active
        foreach ($matchdays as $matchday) {
            // If order is required, check if previous matchdays are complete
            if ($this->requiresMatchdayOrder()) {
                // Check if all previous matchdays are complete
                $previousMatchdays = $this->matchdays()
                    ->where('matchday_number', '<', $matchday->matchday_number)
                    ->where('is_return_round', $matchday->is_return_round)
                    ->get();

                $allPreviousComplete = $previousMatchdays->every(function ($previousMatchday) {
                    return $previousMatchday->isComplete();
                });

                if (! $allPreviousComplete) {
                    continue;
                }
            }

            if ($matchday->isCurrentlyActive() || $matchday->isUpcoming()) {
                return $matchday;
            }
        }

        return null;
    }
}
