<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class News extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'title',
        'slug',
        'content',
        'excerpt',
        'category_id',
        'league_id',
        'season_id',
        'matchday_id',
        'matchday_fixture_id',
        'created_by_user_id',
        'published_at',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(MatchdayFixture::class, 'matchday_fixture_id');
    }

    /**
     * Get the match through the fixture relationship.
     */
    public function match(): ?DartMatch
    {
        return $this->fixture?->dartMatch;
    }

    /**
     * Check if this is platform news.
     */
    public function isPlatformNews(): bool
    {
        return $this->type === 'platform';
    }

    /**
     * Check if this is league news.
     */
    public function isLeagueNews(): bool
    {
        return $this->type === 'league';
    }

    /**
     * Check if the user can view this news.
     */
    public function canBeViewedBy(User $user): bool
    {
        // Platform news can be viewed by everyone
        if ($this->isPlatformNews()) {
            return true;
        }

        // League news: user must be participant of the league/season
        if ($this->isLeagueNews()) {
            // If season-specific, check if user is participant of that season
            if ($this->season_id) {
                if (! $user->player) {
                    return false;
                }

                return $this->season->participants()
                    ->where('player_id', $user->player->id)
                    ->exists();
            }

            // If general league news (no season), check if user is participant of any season of that league
            if ($this->league_id) {
                if (! $user->player) {
                    return false;
                }

                return $this->league->seasons()
                    ->whereHas('participants', function ($query) use ($user) {
                        $query->where('player_id', $user->player->id);
                    })
                    ->exists();
            }
        }

        return false;
    }

    /**
     * Scope a query to only include published news.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope a query to only include platform news.
     */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->where('type', 'platform');
    }

    /**
     * Scope a query to only include league news.
     */
    public function scopeLeague(Builder $query): Builder
    {
        return $query->where('type', 'league');
    }

    /**
     * Scope a query to only include news for a specific league.
     */
    public function scopeForLeague(Builder $query, League $league): Builder
    {
        return $query->where('league_id', $league->id);
    }

    /**
     * Scope a query to only include news for a specific season.
     */
    public function scopeForSeason(Builder $query, Season $season): Builder
    {
        return $query->where('season_id', $season->id);
    }

    /**
     * Scope a query to only include news for a specific category.
     */
    public function scopeByCategory(Builder $query, NewsCategory $category): Builder
    {
        return $query->where('category_id', $category->id);
    }

    /**
     * Scope a query to only include news for a specific matchday.
     */
    public function scopeForMatchday(Builder $query, Matchday $matchday): Builder
    {
        return $query->where('matchday_id', $matchday->id);
    }

    /**
     * Scope a query to only include news for a specific fixture.
     */
    public function scopeForFixture(Builder $query, MatchdayFixture $fixture): Builder
    {
        return $query->where('matchday_fixture_id', $fixture->id);
    }
}

