<?php

namespace App\Console\Commands;

use App\Models\DartMatch;
use App\Models\Leg;
use App\Support\LegStatisticsCalculator;
use Illuminate\Console\Command;

class UpdateLegStatistics extends Command
{
    protected $signature = 'legs:update-statistics 
                            {--match-id= : Nur Statistiken für Legs eines bestimmten Matches aktualisieren}
                            {--leg-id= : Nur Statistiken für ein bestimmtes Leg aktualisieren}
                            {--all : Statistiken für alle Legs aktualisieren}';

    protected $description = 'Berechne und aktualisiere Average, Checkout Rate und Pfeile für Legs';

    public function handle(): int
    {
        $matchId = $this->option('match-id');
        $legId = $this->option('leg-id');
        $all = $this->option('all');

        if (! $matchId && ! $legId && ! $all) {
            $this->error('Bitte gib --match-id=<id>, --leg-id=<id> oder --all an.');

            return self::FAILURE;
        }

        if ($legId) {
            $leg = Leg::find($legId);

            if (! $leg) {
                $this->error("Leg mit ID {$legId} nicht gefunden.");

                return self::FAILURE;
            }

            $this->info("Aktualisiere Statistiken für Leg {$legId} (Match {$leg->match_id}, Set {$leg->set_number}, Leg {$leg->leg_number})...");
            LegStatisticsCalculator::calculateAndUpdate($leg);
            $this->info("✓ Statistiken für Leg {$legId} wurden aktualisiert.");

            return self::SUCCESS;
        }

        if ($matchId) {
            $match = DartMatch::find($matchId);

            if (! $match) {
                $this->error("Match mit ID {$matchId} nicht gefunden.");

                return self::FAILURE;
            }

            $legs = $match->legs;
            $total = $legs->count();

            if ($total === 0) {
                $this->info("Keine Legs für Match {$matchId} gefunden.");

                return self::SUCCESS;
            }

            $this->info("Aktualisiere Statistiken für {$total} Legs von Match {$matchId}...");

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $updated = 0;
            foreach ($legs as $leg) {
                try {
                    LegStatisticsCalculator::calculateAndUpdate($leg);
                    $updated++;
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->warn("Fehler bei Leg {$leg->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("✓ Statistiken für {$updated} von {$total} Legs wurden aktualisiert.");

            return self::SUCCESS;
        }

        if ($all) {
            $legs = Leg::all();
            $total = $legs->count();

            if ($total === 0) {
                $this->info('Keine Legs gefunden.');

                return self::SUCCESS;
            }

            $this->info("Aktualisiere Statistiken für {$total} Legs...");

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $updated = 0;
            foreach ($legs as $leg) {
                try {
                    LegStatisticsCalculator::calculateAndUpdate($leg);
                    $updated++;
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->warn("Fehler bei Leg {$leg->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("✓ Statistiken für {$updated} von {$total} Legs wurden aktualisiert.");

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
