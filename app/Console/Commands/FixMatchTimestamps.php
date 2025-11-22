<?php

namespace App\Console\Commands;

use App\Models\DartMatch;
use App\Models\Turn;
use Illuminate\Console\Command;

class FixMatchTimestamps extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'matches:fix-timestamps {--match-id= : Specific match ID to fix}';

    /**
     * The console command description.
     */
    protected $description = 'Korrigiert Match-Zeitstempel basierend auf den tatsächlichen Turn-Zeiten';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $matchId = $this->option('match-id');

        $query = DartMatch::query()->whereNotNull('finished_at');

        if ($matchId) {
            $query->where('id', $matchId);
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->warn('Keine Matches zum Korrigieren gefunden.');

            return self::SUCCESS;
        }

        $this->info("Korrigiere {$matches->count()} Match(es)...");

        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        $fixed = 0;

        foreach ($matches as $match) {
            $firstTurn = Turn::query()
                ->select('turns.*')
                ->join('legs', 'turns.leg_id', '=', 'legs.id')
                ->where('legs.match_id', $match->id)
                ->whereNotNull('turns.started_at')
                ->orderBy('turns.started_at', 'asc')
                ->first();

            $lastTurn = Turn::query()
                ->select('turns.*')
                ->join('legs', 'turns.leg_id', '=', 'legs.id')
                ->where('legs.match_id', $match->id)
                ->whereNotNull('turns.started_at')
                ->orderBy('turns.started_at', 'desc')
                ->first();

            if ($firstTurn && $lastTurn) {
                $oldStarted = $match->started_at;
                $oldFinished = $match->finished_at;

                $match->update([
                    'started_at' => $firstTurn->started_at,
                    'finished_at' => $lastTurn->started_at,
                ]);

                // Nur als "fixed" zählen, wenn sich etwas geändert hat
                if (! $oldStarted?->eq($firstTurn->started_at) || ! $oldFinished?->eq($lastTurn->started_at)) {
                    $fixed++;
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✓ {$fixed} Match(es) wurden korrigiert.");

        return self::SUCCESS;
    }
}
