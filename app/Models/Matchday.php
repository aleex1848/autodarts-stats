<?php

namespace App\Models;

use App\Enums\FixtureStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matchday extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'matchday_number',
        'is_return_round',
        'deadline_at',
        'is_playoff',
    ];

    protected function casts(): array
    {
        return [
            'matchday_number' => 'integer',
            'is_return_round' => 'boolean',
            'deadline_at' => 'datetime',
            'is_playoff' => 'boolean',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(MatchdayFixture::class);
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }

    /**
     * Get the first published news for this matchday.
     */
    public function getFirstNewsAttribute(): ?News
    {
        return $this->news()->where('is_published', true)->first();
    }

    /**
     * Check if all fixtures of this matchday are completed.
     */
    public function isComplete(): bool
    {
        $totalFixtures = $this->fixtures()->count();
        
        if ($totalFixtures === 0) {
            return false;
        }

        $completedFixtures = $this->fixtures()
            ->where('status', FixtureStatus::Completed->value)
            ->count();

        return $completedFixtures === $totalFixtures;
    }

    /**
     * Get the start date of this matchday.
     * First matchday: deadline - days_per_matchday
     * Other matchdays: previous matchday's deadline
     * Returns null for unlimited schedule modes.
     */
    public function getStartDate(): ?\Carbon\Carbon
    {
        // For unlimited schedule modes, return null
        if (! $this->season->isTimedSchedule()) {
            return null;
        }

        if (! $this->deadline_at) {
            return null;
        }

        // First matchday: start = deadline - days_per_matchday
        if ($this->matchday_number === 1 && ! $this->is_return_round) {
            $daysPerMatchday = $this->season->days_per_matchday ?? 7;
            return $this->deadline_at->copy()->subDays($daysPerMatchday);
        }

        // Other matchdays: start = previous matchday's deadline
        $previousMatchday = $this->season->matchdays()
            ->where('matchday_number', '<', $this->matchday_number)
            ->where('is_return_round', $this->is_return_round)
            ->orderBy('matchday_number', 'desc')
            ->first();

        if ($previousMatchday && $previousMatchday->deadline_at) {
            return $previousMatchday->deadline_at->copy();
        }

        // Fallback: if no previous matchday found, use deadline - days_per_matchday
        $daysPerMatchday = $this->season->days_per_matchday ?? 7;
        return $this->deadline_at->copy()->subDays($daysPerMatchday);
    }

    /**
     * Check if this matchday is currently active.
     * A matchday is active if current time is between start date and deadline.
     * For unlimited schedule modes, a matchday is active if it's not complete.
     */
    public function isCurrentlyActive(): bool
    {
        // For unlimited schedule modes, matchday is active if not complete
        if (! $this->season->isTimedSchedule()) {
            return ! $this->isComplete();
        }

        if (! $this->deadline_at) {
            return false;
        }

        $startDate = $this->getStartDate();
        if (! $startDate) {
            return false;
        }

        $now = now();

        return $now->isAfter($startDate) && $now->isBefore($this->deadline_at);
    }

    /**
     * Check if this matchday is upcoming (in the future).
     * A matchday is upcoming if the start date is in the future.
     * For unlimited schedule modes, matchdays are never "upcoming" - they're either active or complete.
     */
    public function isUpcoming(): bool
    {
        // For unlimited schedule modes, matchdays are never "upcoming"
        if (! $this->season->isTimedSchedule()) {
            return false;
        }

        $startDate = $this->getStartDate();
        if (! $startDate) {
            return false;
        }

        return now()->isBefore($startDate);
    }
}
