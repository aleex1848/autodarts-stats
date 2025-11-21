<?php

declare(strict_types=1);

use App\Actions\AssignMatchToFixture;
use App\Enums\FixtureStatus;
use App\Models\DartMatch;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\Matchday;
use App\Models\MatchdayFixture;
use App\Models\Player;

test('can assign finished match to fixture', function () {
    $league = League::factory()->create();
    
    $homePlayer = Player::factory()->create();
    $awayPlayer = Player::factory()->create();
    
    LeagueParticipant::factory()->create([
        'league_id' => $league->id,
        'player_id' => $homePlayer->id,
    ]);
    
    LeagueParticipant::factory()->create([
        'league_id' => $league->id,
        'player_id' => $awayPlayer->id,
    ]);
    
    $matchday = Matchday::factory()->create([
        'league_id' => $league->id,
    ]);
    
    $fixture = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $homePlayer->id,
        'away_player_id' => $awayPlayer->id,
        'status' => FixtureStatus::Scheduled->value,
    ]);
    
    $match = DartMatch::factory()->create([
        'finished_at' => now(),
    ]);
    
    $match->players()->attach($homePlayer->id, [
        'player_index' => 0,
        'legs_won' => 3,
    ]);
    
    $match->players()->attach($awayPlayer->id, [
        'player_index' => 1,
        'legs_won' => 1,
    ]);
    
    app(AssignMatchToFixture::class)->handle($match, $fixture);
    
    $fixture->refresh();
    
    expect($fixture->dart_match_id)->toBe($match->id);
    expect($fixture->status)->toBe(FixtureStatus::Completed->value);
    expect($fixture->home_legs_won)->toBe(3);
    expect($fixture->away_legs_won)->toBe(1);
    expect($fixture->winner_player_id)->toBe($homePlayer->id);
    expect($fixture->points_awarded_home)->toBe(3);
    expect($fixture->points_awarded_away)->toBe(0);
});

test('cannot assign unfinished match to fixture', function () {
    $league = League::factory()->create();
    
    $homePlayer = Player::factory()->create();
    $awayPlayer = Player::factory()->create();
    
    $matchday = Matchday::factory()->create([
        'league_id' => $league->id,
    ]);
    
    $fixture = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $homePlayer->id,
        'away_player_id' => $awayPlayer->id,
    ]);
    
    $match = DartMatch::factory()->create([
        'finished_at' => null,
    ]);
    
    $match->players()->attach($homePlayer->id, ['player_index' => 0]);
    $match->players()->attach($awayPlayer->id, ['player_index' => 1]);
    
    expect(fn () => app(AssignMatchToFixture::class)->handle($match, $fixture))
        ->toThrow(\InvalidArgumentException::class, 'Das Match ist noch nicht beendet.');
});

test('cannot assign match with wrong players', function () {
    $league = League::factory()->create();
    
    $homePlayer = Player::factory()->create();
    $awayPlayer = Player::factory()->create();
    $otherPlayer = Player::factory()->create();
    
    $matchday = Matchday::factory()->create([
        'league_id' => $league->id,
    ]);
    
    $fixture = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $homePlayer->id,
        'away_player_id' => $awayPlayer->id,
    ]);
    
    $match = DartMatch::factory()->create([
        'finished_at' => now(),
    ]);
    
    $match->players()->attach($homePlayer->id, ['player_index' => 0]);
    $match->players()->attach($otherPlayer->id, ['player_index' => 1]); // Wrong player
    
    expect(fn () => app(AssignMatchToFixture::class)->handle($match, $fixture))
        ->toThrow(\InvalidArgumentException::class, 'Die Spieler des Matches stimmen nicht mit dem Fixture überein.');
});

test('awards draw points when match is tied', function () {
    $league = League::factory()->create();
    
    $homePlayer = Player::factory()->create();
    $awayPlayer = Player::factory()->create();
    
    LeagueParticipant::factory()->create([
        'league_id' => $league->id,
        'player_id' => $homePlayer->id,
    ]);
    
    LeagueParticipant::factory()->create([
        'league_id' => $league->id,
        'player_id' => $awayPlayer->id,
    ]);
    
    $matchday = Matchday::factory()->create([
        'league_id' => $league->id,
    ]);
    
    $fixture = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $homePlayer->id,
        'away_player_id' => $awayPlayer->id,
    ]);
    
    $match = DartMatch::factory()->create([
        'finished_at' => now(),
    ]);
    
    // Tied match - both won 2 legs
    $match->players()->attach($homePlayer->id, [
        'player_index' => 0,
        'legs_won' => 2,
    ]);
    
    $match->players()->attach($awayPlayer->id, [
        'player_index' => 1,
        'legs_won' => 2,
    ]);
    
    app(AssignMatchToFixture::class)->handle($match, $fixture);
    
    $fixture->refresh();
    
    expect($fixture->winner_player_id)->toBeNull();
    expect($fixture->points_awarded_home)->toBe(1);
    expect($fixture->points_awarded_away)->toBe(1);
});
