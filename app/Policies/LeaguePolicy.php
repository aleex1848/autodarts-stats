<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\League;
use App\Models\User;

class LeaguePolicy
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
    public function view(User $user, League $league): bool
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
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, League $league): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, League $league): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ]);
    }

    /**
     * Determine whether the user can register for a league.
     */
    public function register(User $user, League $league): bool
    {
        // User must have a linked player
        return $user->player !== null;
    }
}
