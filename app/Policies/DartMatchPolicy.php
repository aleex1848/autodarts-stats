<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\DartMatch;
use App\Models\User;

class DartMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
            RoleName::Spieler->value,
        ]);
    }

    public function view(User $user, DartMatch $match): bool
    {
        if ($user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ])) {
            return true;
        }

        return $match->players()
            ->where('players.user_id', $user->id)
            ->exists();
    }
}
