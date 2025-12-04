<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\News;
use App\Models\User;

class NewsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, News $news): bool
    {
        // Platform news can be viewed by everyone
        if ($news->isPlatformNews()) {
            return true;
        }

        // League news: check if user can view it
        return $news->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?string $type = null): bool
    {
        // Platform news: only admins
        if ($type === 'platform' || $type === null) {
            return $user->hasAnyRole([
                RoleName::SuperAdmin->value,
                RoleName::Admin->value,
            ]);
        }

        // League news: admins or league admins
        if ($type === 'league') {
            return $user->hasAnyRole([
                RoleName::SuperAdmin->value,
                RoleName::Admin->value,
            ]);
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, News $news): bool
    {
        // Admins can update any news
        if ($user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ])) {
            return true;
        }

        // Creator can update their own news
        if ($news->created_by_user_id === $user->id) {
            return true;
        }

        // For league news, league admins can update
        if ($news->isLeagueNews() && $news->league_id) {
            return $news->league->isAdmin($user);
        }

        // For season-specific news, season admins can update
        if ($news->isLeagueNews() && $news->season_id) {
            return $news->season->isAdmin($user);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, News $news): bool
    {
        // Admins can delete any news
        if ($user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ])) {
            return true;
        }

        // Creator can delete their own news
        if ($news->created_by_user_id === $user->id) {
            return true;
        }

        // For league news, league admins can delete
        if ($news->isLeagueNews() && $news->league_id) {
            return $news->league->isAdmin($user);
        }

        // For season-specific news, season admins can delete
        if ($news->isLeagueNews() && $news->season_id) {
            return $news->season->isAdmin($user);
        }

        return false;
    }
}

