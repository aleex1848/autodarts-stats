<?php

namespace App\Jobs;

use App\Services\MatchdayDeadlineChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckMatchdayDeadlines implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(MatchdayDeadlineChecker $checker): void
    {
        $overdueFixtures = $checker->checkOverdueFixtures();

        if ($overdueFixtures->count() > 0) {
            Log::info('Checked matchday deadlines', [
                'overdue_count' => $overdueFixtures->count(),
            ]);
        }
    }
}
