<?php

namespace App\Enums;

enum FixtureStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Overdue = 'overdue';
    case Walkover = 'walkover';
}
