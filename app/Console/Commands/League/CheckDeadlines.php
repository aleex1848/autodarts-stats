<?php

namespace App\Console\Commands\League;

use App\Services\MatchdayDeadlineChecker;
use Illuminate\Console\Command;

class CheckDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'league:check-deadlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue league fixtures and apply penalties';

    /**
     * Execute the console command.
     */
    public function handle(MatchdayDeadlineChecker $checker): int
    {
        $this->info('Checking matchday deadlines...');

        $overdueFixtures = $checker->checkOverdueFixtures();

        $this->info("Found {$overdueFixtures->count()} overdue fixtures.");

        if ($overdueFixtures->count() > 0) {
            $this->table(
                ['Fixture ID', 'Home Player', 'Away Player', 'Deadline'],
                $overdueFixtures->map(function ($fixture) {
                    return [
                        $fixture->id,
                        $fixture->homePlayer->name ?? 'N/A',
                        $fixture->awayPlayer->name ?? 'N/A',
                        $fixture->matchday->deadline_at?->format('d.m.Y H:i') ?? 'N/A',
                    ];
                })
            );
        }

        return self::SUCCESS;
    }
}
