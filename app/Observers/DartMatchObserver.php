<?php

namespace App\Observers;

use App\Events\MatchUpdated;
use App\Models\DartMatch;

class DartMatchObserver
{
    public function updated(DartMatch $match): void
    {
        broadcast(new MatchUpdated($match));
    }
}
