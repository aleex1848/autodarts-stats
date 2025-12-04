<?php

declare(strict_types=1);

use App\Models\League;
use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('platform news can be viewed by everyone', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $news = News::factory()->platform()->published()->create();

    $response = $this->get(route('news.show', $news));
    $response->assertSuccessful();
    $response->assertSee($news->title);
});

test('league news can be viewed by season participants', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);
    $news = News::factory()->league()->published()->create([
        'league_id' => $league->id,
        'season_id' => $season->id,
    ]);

    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('news.show', $news));
    $response->assertSuccessful();
    $response->assertSee($news->title);
});

test('league news cannot be viewed by non-participants', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);
    $news = News::factory()->league()->published()->create([
        'league_id' => $league->id,
        'season_id' => $season->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('news.show', $news));
    $response->assertForbidden();
});

test('general league news can be viewed by any season participant of that league', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);
    $news = News::factory()->league()->published()->create([
        'league_id' => $league->id,
        'season_id' => null, // General league news
    ]);

    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('news.show', $news));
    $response->assertSuccessful();
    $response->assertSee($news->title);
});

test('news canBeViewedBy method works correctly', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);

    $platformNews = News::factory()->platform()->published()->create();
    $leagueNews = News::factory()->league()->published()->create([
        'league_id' => $league->id,
        'season_id' => $season->id,
    ]);

    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    expect($platformNews->canBeViewedBy($user))->toBeTrue();
    expect($leagueNews->canBeViewedBy($user))->toBeTrue();

    $nonParticipantUser = User::factory()->create();
    expect($platformNews->canBeViewedBy($nonParticipantUser))->toBeTrue();
    expect($leagueNews->canBeViewedBy($nonParticipantUser))->toBeFalse();
});

