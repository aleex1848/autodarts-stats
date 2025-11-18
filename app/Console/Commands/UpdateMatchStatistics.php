<?php

namespace App\Console\Commands;

use App\Models\DartMatch;
use App\Support\MatchStatisticsCalculator;
use Illuminate\Console\Command;

class UpdateMatchStatistics extends Command
{
    protected $signature = 'matches:update-statistics 
                            {--match-id= : Nur Statistiken für ein bestimmtes Match aktualisieren}
                            {--all : Statistiken für alle Matches aktualisieren}';

    protected $description = 'Berechne und aktualisiere Average, Checkout Rate und 180s für Matches';

    public function handle(): int
    {
        $matchId = $this->option('match-id');
        $all = $this->option('all');

        if (! $matchId && ! $all) {
            $this->error('Bitte gib entweder --match-id=<id> oder --all an.');

            return self::FAILURE;
        }

        if ($matchId) {
            $match = DartMatch::find($matchId);

            if (! $match) {
                $this->error("Match mit ID {$matchId} nicht gefunden.");

                return self::FAILURE;
            }

            $this->info("Aktualisiere Statistiken für Match {$matchId}...");
            MatchStatisticsCalculator::calculateAndUpdate($match);
            $this->info("✓ Statistiken für Match {$matchId} wurden aktualisiert.");

            return self::SUCCESS;
        }

        if ($all) {
            $matches = DartMatch::all();
            $total = $matches->count();

            if ($total === 0) {
                $this->info('Keine Matches gefunden.');

                return self::SUCCESS;
            }

            $this->info("Aktualisiere Statistiken für {$total} Matches...");

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $updated = 0;
            foreach ($matches as $match) {
                try {
                    MatchStatisticsCalculator::calculateAndUpdate($match);
                    $updated++;
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->warn("Fehler bei Match {$match->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("✓ Statistiken für {$updated} von {$total} Matches wurden aktualisiert.");

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
