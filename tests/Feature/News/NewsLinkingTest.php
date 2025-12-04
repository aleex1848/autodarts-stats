<?php

declare(strict_types=1);

use App\Models\League;
use App\Models\Matchday;
use App\Models\MatchdayFixture;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Player;
use App\Models\Season;
use App\Models\User;

test('news can be linked to a matchday', function () {
    $user = User::factory()->create();
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);
    $matchday = Matchday::factory()->create(['season_id' => $season->id]);
    $category = NewsCategory::factory()->create(['slug' => 'spieltagsbericht']);

    $news = News::factory()->league()->create([
        'league_id' => $league->id,
        'season_id' => $season->id,
        'category_id' => $category->id,
        'matchday_id' => $matchday->id,
        'created_by_user_id' => $user->id,
    ]);

    expect($news->matchday)->toBeInstanceOf(Matchday::class);
    expect($news->matchday->id)->toBe($matchday->id);
});

test('news can be linked to a fixture', function () {
    $user = User::factory()->create();
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);
    $matchday = Matchday::factory()->create(['season_id' => $season->id]);
    $homePlayer = Player::factory()->create();
    $awayPlayer = Player::factory()->create();
    $fixture = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $homePlayer->id,
        'away_player_id' => $awayPlayer->id,
    ]);
    $category = NewsCategory::factory()->create(['slug' => 'spielberichte']);

    $news = News::factory()->league()->create([
        'league_id' => $league->id,
        'season_id' => $season->id,
        'category_id' => $category->id,
        'matchday_fixture_id' => $fixture->id,
        'created_by_user_id' => $user->id,
    ]);

    expect($news->fixture)->toBeInstanceOf(MatchdayFixture::class);
    expect($news->fixture->id)->toBe($fixture->id);
});

test('news can access match through fixture', function () {
    $user = User::factory()->create();
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);
    $matchday = Matchday::factory()->create(['season_id' => $season->id]);
    $homePlayer = Player::factory()->create();
    $awayPlayer = Player::factory()->create();
    $match = \App\Models\DartMatch::factory()->create();
    $fixture = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $homePlayer->id,
        'away_player_id' => $awayPlayer->id,
        'dart_match_id' => $match->id,
    ]);
    $category = NewsCategory::factory()->create(['slug' => 'spielberichte']);

    $news = News::factory()->league()->create([
        'league_id' => $league->id,
        'season_id' => $season->id,
        'category_id' => $category->id,
        'matchday_fixture_id' => $fixture->id,
        'created_by_user_id' => $user->id,
    ]);

    $news->load('fixture.dartMatch');

    expect($news->match())->toBeInstanceOf(\App\Models\DartMatch::class);
    expect($news->match()->id)->toBe($match->id);
});

