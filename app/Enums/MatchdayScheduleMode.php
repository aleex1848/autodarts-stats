<?php

namespace App\Enums;

enum MatchdayScheduleMode: string
{
    case Timed = 'timed';
    case UnlimitedNoOrder = 'unlimited_no_order';
    case UnlimitedWithOrder = 'unlimited_with_order';
}
