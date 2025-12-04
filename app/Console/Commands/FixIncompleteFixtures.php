<?php

namespace App\Console\Commands;

use App\Models\MatchdayFixture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixIncompleteFixtures extends Command
{
    protected $signature = 'fixtures:fix-incomplete';

    protected $description = 'Aktualisiert Fixtures, die ein Match haben, aber keine vollständigen Leg-Ergebnisse';

    public function handle(): int
    {
        $this->info('Suche nach Fixtures mit unvollständigen Daten...');

        $fixtures = MatchdayFixture::whereNotNull('dart_match_id')
            ->where(function ($query) {
                $query->whereNull('home_legs_won')
                    ->orWhereNull('away_legs_won')
                    ->orWhereNull('winner_player_id');
            })
            ->with('dartMatch.players')
            ->get();

        if ($fixtures->isEmpty()) {
            $this->info('Keine Fixtures mit unvollständigen Daten gefunden.');

            return Command::SUCCESS;
        }

        $this->info("Gefunden: {$fixtures->count()} Fixtures mit unvollständigen Daten.");

        $updated = 0;
        $failed = 0;

        foreach ($fixtures as $fixture) {
            $match = $fixture->dartMatch;

            if (! $match) {
                $this->warn("Fixture {$fixture->id} hat kein Match.");
                $failed++;
                continue;
            }

            if ($match->finished_at === null) {
                $this->warn("Match {$match->id} für Fixture {$fixture->id} ist noch nicht beendet.");
                continue;
            }

            try {
                app(\App\Actions\AssignMatchToFixture::class)->handle($match, $fixture);
                $updated++;
                $this->info("Fixture {$fixture->id} erfolgreich aktualisiert.");
            } catch (\Exception $e) {
                $failed++;
                $this->error("Fehler beim Aktualisieren von Fixture {$fixture->id}: {$e->getMessage()}");
                Log::error('Failed to fix fixture', [
                    'fixture_id' => $fixture->id,
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Aktualisiert: {$updated}, Fehler: {$failed}");

        return Command::SUCCESS;
    }
}

