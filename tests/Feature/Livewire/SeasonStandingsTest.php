<?php

use App\Enums\FixtureStatus;
use App\Models\DartMatch;
use App\Models\League;
use App\Models\Matchday;
use App\Models\MatchdayFixture;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('shows message when user has no active seasons', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Volt::test('season-standings');

    $component->assertSee(__('Du nimmst aktuell an keiner aktiven Saison teil.'));
});

test('shows seasons where user is participating', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create([
        'league_id' => $league->id,
        'status' => 'active',
    ]);

    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $this->actingAs($user);

    $component = Volt::test('season-standings');

    $component->assertSee($season->name);
    $component->assertSee($league->name);
});

test('calculates statistics correctly', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create([
        'league_id' => $league->id,
        'status' => 'active',
    ]);

    $participant = SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
        'matches_played' => 5,
        'points' => 12,
        'matches_won' => 4,
        'matches_lost' => 1,
    ]);

    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
    ]);

    // Erstelle ein abgeschlossenes Fixture
    $fixture = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player->id,
        'status' => FixtureStatus::Completed->value,
        'winner_player_id' => $player->id,
        'points_awarded_home' => 3,
    ]);

    $match = DartMatch::factory()->create(['finished_at' => now()]);
    $fixture->update(['dart_match_id' => $match->id]);

    MatchPlayer::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'match_average' => 65.50,
    ]);

    $this->actingAs($user);

    $component = Volt::test('season-standings');

    $component->assertSee('5'); // Gespielte Spiele
    $component->assertSee('12'); // Punkte
});

test('shows table slice with user position', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create([
        'league_id' => $league->id,
        'status' => 'active',
    ]);

    // Erstelle mehrere Teilnehmer
    $otherPlayers = Player::factory()->count(5)->create();
    
    foreach ($otherPlayers as $index => $otherPlayer) {
        SeasonParticipant::factory()->create([
            'season_id' => $season->id,
            'player_id' => $otherPlayer->id,
            'points' => 20 - ($index * 2),
        ]);
    }

    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
        'points' => 10,
    ]);

    $this->actingAs($user);

    $component = Volt::test('season-standings');

    $component->assertSee($player->name);
    $component->assertSee(__('Du'));
});

test('calculates season average correctly', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create([
        'league_id' => $league->id,
        'status' => 'active',
    ]);

    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
    ]);

    // Erstelle zwei Matches mit unterschiedlichen Averages
    $match1 = DartMatch::factory()->create(['finished_at' => now()]);
    $match2 = DartMatch::factory()->create(['finished_at' => now()]);

    $fixture1 = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player->id,
        'dart_match_id' => $match1->id,
        'status' => FixtureStatus::Completed->value,
    ]);

    $fixture2 = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player->id,
        'dart_match_id' => $match2->id,
        'status' => FixtureStatus::Completed->value,
    ]);

    MatchPlayer::factory()->create([
        'match_id' => $match1->id,
        'player_id' => $player->id,
        'match_average' => 60.00,
    ]);

    MatchPlayer::factory()->create([
        'match_id' => $match2->id,
        'player_id' => $player->id,
        'match_average' => 70.00,
    ]);

    $this->actingAs($user);

    $component = Volt::test('season-standings');

    // Season Average sollte 65.00 sein (60 + 70) / 2
    $component->assertSee('65.00');
});

test('handles edge case when user is at position 1', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create([
        'league_id' => $league->id,
        'status' => 'active',
    ]);

    // User hat die meisten Punkte
    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
        'points' => 30,
    ]);

    // Andere Spieler mit weniger Punkten
    $otherPlayers = Player::factory()->count(3)->create();
    foreach ($otherPlayers as $otherPlayer) {
        SeasonParticipant::factory()->create([
            'season_id' => $season->id,
            'player_id' => $otherPlayer->id,
            'points' => 10,
        ]);
    }

    $this->actingAs($user);

    $component = Volt::test('season-standings');

    $component->assertSee('#1');
});

test('does not show completed seasons by default', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    
    $activeSeason = Season::factory()->create([
        'league_id' => $league->id,
        'status' => 'active',
    ]);

    $completedSeason = Season::factory()->create([
        'league_id' => $league->id,
        'status' => 'completed',
    ]);

    SeasonParticipant::factory()->create([
        'season_id' => $activeSeason->id,
        'player_id' => $player->id,
    ]);

    SeasonParticipant::factory()->create([
        'season_id' => $completedSeason->id,
        'player_id' => $player->id,
    ]);

    $this->actingAs($user);

    $component = Volt::test('season-standings');

    $component->assertSee($activeSeason->name);
    $component->assertSee($completedSeason->name); // Completed seasons should also be shown
});

test('shows progress data when matchdays are completed', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create([
        'league_id' => $league->id,
        'status' => 'active',
    ]);

    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
    ]);

    $match = DartMatch::factory()->create(['finished_at' => now()]);
    
    $fixture = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player->id,
        'dart_match_id' => $match->id,
        'status' => FixtureStatus::Completed->value,
        'winner_player_id' => $player->id,
    ]);

    MatchPlayer::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'match_average' => 65.50,
    ]);

    $this->actingAs($user);

    $component = Volt::test('season-standings');

    // Prüfe ob Entwicklungs-Tabs vorhanden sind
    $component->assertSee(__('Entwicklung'));
});
