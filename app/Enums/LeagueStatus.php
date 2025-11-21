<?php

namespace App\Enums;

enum LeagueStatus: string
{
    case Registration = 'registration';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
