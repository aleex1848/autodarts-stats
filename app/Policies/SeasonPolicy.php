<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Season;
use App\Models\User;

class SeasonPolicy
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
    public function view(User $user, Season $season): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ]) || $this->isLeagueAdmin($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Season $season): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ]) || $season->isAdmin($user) || $season->league->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Season $season): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ]) || $season->isAdmin($user) || $season->league->isAdmin($user);
    }

    /**
     * Check if user is admin of the league (for creating seasons)
     */
    protected function isLeagueAdmin(User $user): bool
    {
        // This is checked in the controller/component before creating a season
        // as we need to know which league we're creating for
        return false;
    }
}

