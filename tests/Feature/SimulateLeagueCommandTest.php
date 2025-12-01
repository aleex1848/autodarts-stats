<?php

declare(strict_types=1);

use App\Enums\FixtureStatus;
use App\Enums\LeagueMode;
use App\Enums\LeagueStatus;
use App\Models\DartMatch;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\Matchday;
use App\Models\MatchdayFixture;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('command creates league with 8 players in single round mode', function () {
    $this->artisan('league:simulate', ['--players' => 8, '--mode' => 'single_round'])
        ->assertSuccessful();

    // Prüfe Liga
    $league = League::where('mode', LeagueMode::SingleRound->value)
        ->where('status', LeagueStatus::Active->value)
        ->first();

    expect($league)->not->toBeNull();
    expect($league->max_players)->toBe(8);

    // Prüfe Benutzer und Spieler
    expect(User::count())->toBe(8);
    expect(Player::count())->toBe(8);
    expect(Player::whereNotNull('user_id')->count())->toBe(8);

    // Prüfe Teilnehmer
    expect($league->participants)->toHaveCount(8);

    // Prüfe Spieltage (8 Spieler = 7 Spieltage bei Single Round)
    expect($league->matchdays)->toHaveCount(7);

    // Prüfe Matches (7 Spieltage * 4 Matches = 28 Matches)
    $totalFixtures = MatchdayFixture::whereHas('matchday', function ($query) use ($league) {
        $query->where('league_id', $league->id);
    })->count();

    expect($totalFixtures)->toBe(28);

    // Prüfe, dass alle Matches erstellt wurden
    $matchesCount = DartMatch::whereHas('fixture.matchday', function ($query) use ($league) {
        $query->where('league_id', $league->id);
    })->count();

    expect($matchesCount)->toBe(28);

    // Prüfe, dass alle Fixtures als completed markiert sind
    $completedFixtures = MatchdayFixture::whereHas('matchday', function ($query) use ($league) {
        $query->where('league_id', $league->id);
    })->where('status', FixtureStatus::Completed->value)->count();

    expect($completedFixtures)->toBe(28);
});

test('command creates league with 6 players in double round mode', function () {
    $this->artisan('league:simulate', ['--players' => 6, '--mode' => 'double_round'])
        ->assertSuccessful();

    $league = League::where('mode', LeagueMode::DoubleRound->value)->first();

    expect($league)->not->toBeNull();
    expect($league->max_players)->toBe(6);

    // Double Round: 6 Spieler = 5 Spieltage Hinrunde + 5 Spieltage Rückrunde = 10 Spieltage
    expect($league->matchdays)->toHaveCount(10);

    // 10 Spieltage * 3 Matches = 30 Matches
    $totalFixtures = MatchdayFixture::whereHas('matchday', function ($query) use ($league) {
        $query->where('league_id', $league->id);
    })->count();

    expect($totalFixtures)->toBe(30);
});

test('command creates matches with realistic statistics', function () {
    $this->artisan('league:simulate', ['--players' => 4])
        ->assertSuccessful();

    $league = League::first();
    $match = DartMatch::whereHas('fixture.matchday', function ($query) use ($league) {
        $query->where('league_id', $league->id);
    })->first();

    expect($match)->not->toBeNull();
    expect($match->winner_player_id)->not->toBeNull();
    expect($match->started_at)->not->toBeNull();
    expect($match->finished_at)->not->toBeNull();
    expect($match->is_incomplete)->toBeFalse();

    // Prüfe MatchPlayers
    $matchPlayers = MatchPlayer::where('match_id', $match->id)->get();
    expect($matchPlayers)->toHaveCount(2);

    foreach ($matchPlayers as $matchPlayer) {
        // Prüfe, dass realistische Statistiken vorhanden sind
        expect($matchPlayer->match_average)->toBeGreaterThanOrEqual(40);
        expect($matchPlayer->match_average)->toBeLessThanOrEqual(80);
        expect($matchPlayer->checkout_rate)->toBeGreaterThanOrEqual(0.20);
        expect($matchPlayer->checkout_rate)->toBeLessThanOrEqual(0.50);
        expect($matchPlayer->total_180s)->toBeGreaterThanOrEqual(0);
        expect($matchPlayer->darts_thrown)->toBeGreaterThan(0);
        expect($matchPlayer->legs_won)->toBeGreaterThanOrEqual(0);
        expect($matchPlayer->legs_won)->toBeLessThanOrEqual(3);
    }

    // Prüfe, dass genau einer 3 Legs gewonnen hat (Best of 5)
    $winner = $matchPlayers->firstWhere('legs_won', 3);
    expect($winner)->not->toBeNull();
});

test('command updates league standings after matches', function () {
    $this->artisan('league:simulate', ['--players' => 4])
        ->assertSuccessful();

    $league = League::first();

    // Prüfe, dass alle Teilnehmer Statistiken haben
    foreach ($league->participants as $participant) {
        expect($participant->matches_played)->toBeGreaterThan(0);
        expect($participant->matches_won + $participant->matches_lost)->toBe($participant->matches_played);
        expect($participant->legs_won)->toBeGreaterThanOrEqual(0);
        expect($participant->points)->toBeGreaterThanOrEqual(0);
    }

    // Prüfe, dass final_position gesetzt ist
    $participantsWithPosition = $league->participants()->whereNotNull('final_position')->count();
    expect($participantsWithPosition)->toBe(4);
});

test('command fails with less than 2 players', function () {
    $this->artisan('league:simulate', ['--players' => 1])
        ->assertFailed();
});

test('command fails with invalid mode', function () {
    $this->artisan('league:simulate', ['--players' => 4, '--mode' => 'invalid'])
        ->assertFailed();
});

test('command creates fixtures with correct points', function () {
    $this->artisan('league:simulate', ['--players' => 4])
        ->assertSuccessful();

    $league = League::first();

    $fixtures = MatchdayFixture::whereHas('matchday', function ($query) use ($league) {
        $query->where('league_id', $league->id);
    })->where('status', FixtureStatus::Completed->value)->get();

    foreach ($fixtures as $fixture) {
        // Prüfe, dass Punkte vergeben wurden
        expect($fixture->points_awarded_home)->toBeGreaterThanOrEqual(0);
        expect($fixture->points_awarded_away)->toBeGreaterThanOrEqual(0);

        // Prüfe, dass genau einer gewonnen hat (3 Punkte) oder beide 0 Punkte haben (sollte nicht vorkommen bei Best of 5)
        $totalPoints = $fixture->points_awarded_home + $fixture->points_awarded_away;
        expect($totalPoints)->toBe(3); // Genau einer gewinnt

        // Prüfe, dass winner_player_id gesetzt ist
        expect($fixture->winner_player_id)->not->toBeNull();

        // Prüfe, dass Legs korrekt gesetzt sind
        expect($fixture->home_legs_won + $fixture->away_legs_won)->toBeGreaterThanOrEqual(3);
        expect($fixture->home_legs_won)->toBeLessThanOrEqual(3);
        expect($fixture->away_legs_won)->toBeLessThanOrEqual(3);

        // Prüfe, dass genau einer 3 Legs gewonnen hat
        $hasWinner = ($fixture->home_legs_won === 3) || ($fixture->away_legs_won === 3);
        expect($hasWinner)->toBeTrue();
    }
});

test('command links players to users correctly', function () {
    $this->artisan('league:simulate', ['--players' => 8])
        ->assertSuccessful();

    $users = User::all();
    $players = Player::all();

    expect($users)->toHaveCount(8);
    expect($players)->toHaveCount(8);

    // Prüfe, dass jeder Spieler einem Benutzer zugeordnet ist
    foreach ($players as $player) {
        expect($player->user_id)->not->toBeNull();
        expect($player->user)->toBeInstanceOf(User::class);
        expect($users->contains('id', $player->user_id))->toBeTrue();
    }

    // Prüfe, dass jeder Benutzer genau einen Spieler hat
    foreach ($users as $user) {
        $userPlayers = Player::where('user_id', $user->id)->count();
        expect($userPlayers)->toBe(1);
    }
});
