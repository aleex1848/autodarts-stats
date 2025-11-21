<?php

use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\Player;
use App\Services\LeagueScheduler;

test('scheduler generates correct number of matchdays for single round', function () {
    $league = League::factory()->create(['mode' => 'single_round']);
    
    $players = Player::factory()->count(4)->create();
    $participants = collect();
    
    foreach ($players as $player) {
        $participants->push(LeagueParticipant::factory()->create([
            'league_id' => $league->id,
            'player_id' => $player->id,
        ]));
    }

    $scheduler = app(LeagueScheduler::class);
    $scheduler->generateMatchdays($league, $participants);

    $league->refresh();

    // For 4 players, we expect 3 matchdays (n-1)
    expect($league->matchdays)->toHaveCount(3);
});

test('scheduler generates fixtures for all players', function () {
    $league = League::factory()->create(['mode' => 'single_round']);
    
    $players = Player::factory()->count(4)->create();
    $participants = collect();
    
    foreach ($players as $player) {
        $participants->push(LeagueParticipant::factory()->create([
            'league_id' => $league->id,
            'player_id' => $player->id,
        ]));
    }

    $scheduler = app(LeagueScheduler::class);
    $scheduler->generateMatchdays($league, $participants);

    $league->refresh();

    // Each player should play against every other player once
    // For 4 players: 4 * 3 / 2 = 6 total fixtures
    $totalFixtures = $league->matchdays->sum(function ($matchday) {
        return $matchday->fixtures->count();
    });

    expect($totalFixtures)->toBe(6);
});

test('scheduler generates double round when configured', function () {
    $league = League::factory()->create(['mode' => 'double_round']);
    
    $players = Player::factory()->count(4)->create();
    $participants = collect();
    
    foreach ($players as $player) {
        $participants->push(LeagueParticipant::factory()->create([
            'league_id' => $league->id,
            'player_id' => $player->id,
        ]));
    }

    $scheduler = app(LeagueScheduler::class);
    $scheduler->generateMatchdays($league, $participants);

    $league->refresh();

    // For 4 players with double round: 6 matchdays (2 * (n-1))
    expect($league->matchdays)->toHaveCount(6);

    // Check that we have return round matchdays
    $returnRoundCount = $league->matchdays->where('is_return_round', true)->count();
    expect($returnRoundCount)->toBe(3);
});
