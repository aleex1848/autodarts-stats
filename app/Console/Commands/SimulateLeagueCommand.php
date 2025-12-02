<?php

namespace App\Console\Commands;

use App\Enums\FixtureStatus;
use App\Enums\LeagueMatchFormat;
use App\Enums\LeagueMode;
use App\Enums\LeagueStatus;
use App\Enums\LeagueVariant;
use App\Enums\MatchdayScheduleMode;
use App\Models\DartMatch;
use App\Models\League;
use App\Models\Season;
use App\Models\SeasonParticipant;
use App\Models\MatchdayFixture;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\User;
use App\Services\LeagueScheduler;
use App\Services\LeagueStandingsCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateLeagueCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'league:simulate 
                            {--players=8 : Anzahl der Spieler in der Liga} 
                            {--mode=single_round : Liga-Modus (single_round oder double_round)}
                            {--schedule-mode=timed : Spieltag-Planung (timed, unlimited_no_order, unlimited_with_order)}
                            {--days-per-matchday=7 : Tage pro Spieltag (nur bei timed-Modus)}';

    /**
     * The console command description.
     */
    protected $description = 'Erstellt eine vollständige Liga mit Benutzern, Spielern und Matches für Tests';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $playerCount = (int) $this->option('players');
        $mode = $this->option('mode');
        $scheduleMode = $this->option('schedule-mode');
        $daysPerMatchday = (int) $this->option('days-per-matchday');

        if ($playerCount < 2) {
            $this->error('Es werden mindestens 2 Spieler benötigt.');

            return self::FAILURE;
        }

        if (! in_array($mode, ['single_round', 'double_round'])) {
            $this->error('Ungültiger Modus. Verwende single_round oder double_round.');

            return self::FAILURE;
        }

        $validScheduleModes = ['timed', 'unlimited_no_order', 'unlimited_with_order'];
        if (! in_array($scheduleMode, $validScheduleModes)) {
            $this->error('Ungültiger Schedule-Modus. Verwende: ' . implode(', ', $validScheduleModes));

            return self::FAILURE;
        }

        if ($scheduleMode !== 'timed' && $daysPerMatchday !== 7) {
            $this->warn('Hinweis: days-per-matchday wird ignoriert, da Schedule-Modus nicht "timed" ist.');
        }

        $scheduleModeEnum = match ($scheduleMode) {
            'timed' => MatchdayScheduleMode::Timed,
            'unlimited_no_order' => MatchdayScheduleMode::UnlimitedNoOrder,
            'unlimited_with_order' => MatchdayScheduleMode::UnlimitedWithOrder,
            default => MatchdayScheduleMode::Timed,
        };

        $this->info("Erstelle Liga mit {$playerCount} Spielern im {$mode}-Modus...");
        $this->info("Spieltag-Planung: {$scheduleMode}");

        return DB::transaction(function () use ($playerCount, $mode, $scheduleModeEnum, $daysPerMatchday, $scheduleMode) {
            // 1. Benutzer & Spieler erstellen
            $this->info('Erstelle Benutzer und Spieler...');
            $users = User::factory()->count($playerCount)->create();
            $players = collect();

            foreach ($users as $user) {
                $players->push(Player::factory()->create(['user_id' => $user->id]));
            }

            // 2. Liga erstellen
            $this->info('Erstelle Liga...');
            $league = League::create([
                'name' => 'Test Liga ' . now()->format('Y-m-d H:i'),
                'slug' => 'test-liga-' . now()->format('Y-m-d-h-i'),
                'description' => 'Automatisch generierte Test-Liga',
                'created_by_user_id' => $users->first()->id,
            ]);

            // 3. Saison erstellen
            $this->info('Erstelle Saison...');
            $season = Season::create([
                'league_id' => $league->id,
                'name' => 'Saison ' . now()->format('Y'),
                'slug' => 'saison-' . now()->format('Y'),
                'description' => 'Automatisch generierte Test-Saison',
                'max_players' => $playerCount,
                'mode' => $mode === 'single_round' ? LeagueMode::SingleRound->value : LeagueMode::DoubleRound->value,
                'variant' => LeagueVariant::Single501DoubleOut->value,
                'match_format' => LeagueMatchFormat::BestOf5->value,
                'registration_deadline' => now()->subDays(7),
                'days_per_matchday' => $scheduleMode === 'timed' ? $daysPerMatchday : null,
                'matchday_schedule_mode' => $scheduleModeEnum,
                'status' => LeagueStatus::Active->value,
                'created_by_user_id' => $users->first()->id,
            ]);

            // 4. Teilnehmer registrieren
            $this->info('Registriere Teilnehmer...');
            foreach ($players as $player) {
                SeasonParticipant::create([
                    'season_id' => $season->id,
                    'player_id' => $player->id,
                ]);
            }

            // 5. Spielplan generieren
            $this->info('Generiere Spielplan...');
            $scheduler = new LeagueScheduler();
            $scheduler->generateMatchdays($season, $season->participants);

            $totalFixtures = $season->matchdays()->withCount('fixtures')->get()->sum('fixtures_count');
            $this->info("Spielplan erstellt: {$season->matchdays()->count()} Spieltage mit {$totalFixtures} Matches");

            // 6. Matches für alle Fixtures erstellen
            $this->info('Erstelle Matches...');
            $progressBar = $this->output->createProgressBar($totalFixtures);
            $progressBar->start();

            $standingsCalculator = new LeagueStandingsCalculator();

            foreach ($season->matchdays as $matchday) {
                foreach ($matchday->fixtures as $fixture) {
                    $this->createMatchForFixture($fixture);
                    $progressBar->advance();

                    // Tabellenstände nach jedem Match aktualisieren
                    $standingsCalculator->calculateStandings($season);
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("✓ Liga und Saison erfolgreich erstellt!");
            $this->info("  Liga ID: {$league->id}");
            $this->info("  Saison ID: {$season->id}");
            $this->info("  Spieltage: {$season->matchdays()->count()}");
            $this->info("  Matches: {$totalFixtures}");
            $this->info("  Spieltag-Planung: {$scheduleMode}");
            if ($scheduleMode === 'timed') {
                $this->info("  Tage pro Spieltag: {$daysPerMatchday}");
            }

            return self::SUCCESS;
        });
    }

    protected function createMatchForFixture(MatchdayFixture $fixture): DartMatch
    {
        // Bestimme Gewinner und Legs (Best of 5 = 3 Legs zum Sieg)
        $homeLegsWon = fake()->numberBetween(0, 3);
        $awayLegsWon = $homeLegsWon < 3 ? 3 : fake()->numberBetween(0, 2);

        // Stelle sicher, dass genau einer gewinnt (kein Unentschieden möglich bei Best of 5)
        if ($homeLegsWon === $awayLegsWon) {
            $homeLegsWon = 3;
            $awayLegsWon = fake()->numberBetween(0, 2);
        }

        $winnerId = $homeLegsWon === 3 ? $fixture->home_player_id : $fixture->away_player_id;

        // Match erstellen
        $match = DartMatch::factory()->create([
            'autodarts_match_id' => fake()->uuid(),
            'variant' => 'X01',
            'type' => 'Online',
            'base_score' => 501,
            'in_mode' => 'Straight',
            'out_mode' => 'Double',
            'bull_mode' => '25/50',
            'max_rounds' => 20,
            'winner_player_id' => $winnerId,
            'started_at' => now()->subHours(fake()->numberBetween(1, 48)),
            'finished_at' => now()->subHours(fake()->numberBetween(0, 1)),
            'is_incomplete' => false,
        ]);

        // Realistische Statistiken für Home-Spieler
        $homeAverage = fake()->randomFloat(2, 40, 80);
        $homeCheckoutRate = fake()->randomFloat(4, 0.20, 0.50);
        $homeCheckoutAttempts = fake()->numberBetween(5, 15);
        $homeCheckoutHits = (int) round($homeCheckoutAttempts * $homeCheckoutRate);
        $homeTotal180s = fake()->numberBetween(0, 3);
        $homeDartsThrown = fake()->numberBetween(60, 150);

        MatchPlayer::create([
            'match_id' => $match->id,
            'player_id' => $fixture->home_player_id,
            'player_index' => 0,
            'legs_won' => $homeLegsWon,
            'sets_won' => 0,
            'final_position' => $homeLegsWon === 3 ? 1 : 2,
            'match_average' => $homeAverage,
            'checkout_rate' => $homeCheckoutRate,
            'checkout_attempts' => $homeCheckoutAttempts,
            'checkout_hits' => $homeCheckoutHits,
            'total_180s' => $homeTotal180s,
            'darts_thrown' => $homeDartsThrown,
            'busted_count' => fake()->numberBetween(0, 3),
        ]);

        // Realistische Statistiken für Away-Spieler
        $awayAverage = fake()->randomFloat(2, 40, 80);
        $awayCheckoutRate = fake()->randomFloat(4, 0.20, 0.50);
        $awayCheckoutAttempts = fake()->numberBetween(5, 15);
        $awayCheckoutHits = (int) round($awayCheckoutAttempts * $awayCheckoutRate);
        $awayTotal180s = fake()->numberBetween(0, 3);
        $awayDartsThrown = fake()->numberBetween(60, 150);

        MatchPlayer::create([
            'match_id' => $match->id,
            'player_id' => $fixture->away_player_id,
            'player_index' => 1,
            'legs_won' => $awayLegsWon,
            'sets_won' => 0,
            'final_position' => $awayLegsWon === 3 ? 1 : 2,
            'match_average' => $awayAverage,
            'checkout_rate' => $awayCheckoutRate,
            'checkout_attempts' => $awayCheckoutAttempts,
            'checkout_hits' => $awayCheckoutHits,
            'total_180s' => $awayTotal180s,
            'darts_thrown' => $awayDartsThrown,
            'busted_count' => fake()->numberBetween(0, 3),
        ]);

        // Punkte vergeben (3 Punkte für Sieg, 1 Punkt für Unentschieden, 0 für Niederlage)
        $homePoints = $homeLegsWon === 3 ? 3 : 0;
        $awayPoints = $awayLegsWon === 3 ? 3 : 0;

        // Fixture aktualisieren
        $fixture->update([
            'dart_match_id' => $match->id,
            'status' => FixtureStatus::Completed->value,
            'home_legs_won' => $homeLegsWon,
            'away_legs_won' => $awayLegsWon,
            'winner_player_id' => $winnerId,
            'points_awarded_home' => $homePoints,
            'points_awarded_away' => $awayPoints,
            'played_at' => $match->finished_at,
        ]);

        return $match;
    }
}
