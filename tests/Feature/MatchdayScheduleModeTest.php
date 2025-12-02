<?php

declare(strict_types=1);

use App\Enums\FixtureStatus;
use App\Enums\MatchdayScheduleMode;
use App\Models\Matchday;
use App\Models\MatchdayFixture;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('matchday isComplete returns true when all fixtures are completed', function () {
    $season = Season::factory()->create();
    $matchday = Matchday::factory()->create(['season_id' => $season->id]);
    
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();
    $player3 = Player::factory()->create();
    $player4 = Player::factory()->create();

    MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player1->id,
        'away_player_id' => $player2->id,
        'status' => FixtureStatus::Completed->value,
    ]);

    MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player3->id,
        'away_player_id' => $player4->id,
        'status' => FixtureStatus::Completed->value,
    ]);

    expect($matchday->isComplete())->toBeTrue();
});

test('matchday isComplete returns false when not all fixtures are completed', function () {
    $season = Season::factory()->create();
    $matchday = Matchday::factory()->create(['season_id' => $season->id]);
    
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();
    $player3 = Player::factory()->create();
    $player4 = Player::factory()->create();

    MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player1->id,
        'away_player_id' => $player2->id,
        'status' => FixtureStatus::Completed->value,
    ]);

    MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player3->id,
        'away_player_id' => $player4->id,
        'status' => FixtureStatus::Scheduled->value,
    ]);

    expect($matchday->isComplete())->toBeFalse();
});

test('unlimited no order mode allows starting matchdays in any order', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $season = Season::factory()->create([
        'status' => 'active',
        'matchday_schedule_mode' => MatchdayScheduleMode::UnlimitedNoOrder,
    ]);
    
    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $matchday1 = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
        'deadline_at' => null,
    ]);

    $matchday2 = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 2,
        'deadline_at' => null,
    ]);

    $this->actingAs($user);

    // Should be able to start matchday 2 even if matchday 1 is not complete
    Volt::test('quick-start-matchday')
        ->call('startMatchday', $matchday2->id)
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->playing_matchday_id)->toBe($matchday2->id);
});

test('unlimited with order mode prevents starting matchday if previous is not complete', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $season = Season::factory()->create([
        'status' => 'active',
        'matchday_schedule_mode' => MatchdayScheduleMode::UnlimitedWithOrder,
    ]);
    
    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();

    $matchday1 = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
        'deadline_at' => null,
    ]);

    // Create incomplete fixture for matchday 1
    MatchdayFixture::factory()->create([
        'matchday_id' => $matchday1->id,
        'home_player_id' => $player1->id,
        'away_player_id' => $player2->id,
        'status' => FixtureStatus::Scheduled->value,
    ]);

    $matchday2 = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 2,
        'deadline_at' => null,
    ]);

    $this->actingAs($user);

    // Should not be able to start matchday 2 if matchday 1 is not complete
    Volt::test('quick-start-matchday')
        ->call('startMatchday', $matchday2->id)
        ->assertHasErrors('matchday');
});

test('unlimited with order mode allows starting matchday if previous is complete', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $season = Season::factory()->create([
        'status' => 'active',
        'matchday_schedule_mode' => MatchdayScheduleMode::UnlimitedWithOrder,
    ]);
    
    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();

    $matchday1 = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
        'deadline_at' => null,
    ]);

    // Create completed fixture for matchday 1
    MatchdayFixture::factory()->create([
        'matchday_id' => $matchday1->id,
        'home_player_id' => $player1->id,
        'away_player_id' => $player2->id,
        'status' => FixtureStatus::Completed->value,
    ]);

    $matchday2 = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 2,
        'deadline_at' => null,
    ]);

    $this->actingAs($user);

    // Should be able to start matchday 2 if matchday 1 is complete
    Volt::test('quick-start-matchday')
        ->call('startMatchday', $matchday2->id)
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->playing_matchday_id)->toBe($matchday2->id);
});

test('timed mode maintains existing functionality', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $season = Season::factory()->create([
        'status' => 'active',
        'matchday_schedule_mode' => MatchdayScheduleMode::Timed,
        'days_per_matchday' => 7,
    ]);
    
    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
        'deadline_at' => now()->addDays(3),
    ]);

    $this->actingAs($user);

    Volt::test('quick-start-matchday')
        ->call('startMatchday', $matchday->id)
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->playing_matchday_id)->toBe($matchday->id);
});

test('getNextRelevantMatchday respects order requirement', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $season = Season::factory()->create([
        'status' => 'active',
        'matchday_schedule_mode' => MatchdayScheduleMode::UnlimitedWithOrder,
    ]);
    
    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();

    $matchday1 = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
        'deadline_at' => null,
    ]);

    // Create incomplete fixture for matchday 1
    MatchdayFixture::factory()->create([
        'matchday_id' => $matchday1->id,
        'home_player_id' => $player1->id,
        'away_player_id' => $player2->id,
        'status' => FixtureStatus::Scheduled->value,
    ]);

    $matchday2 = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 2,
        'deadline_at' => null,
    ]);

    // Should return matchday 1 (the first incomplete one)
    $nextMatchday = $season->getNextRelevantMatchday($user);
    expect($nextMatchday)->not->toBeNull();
    expect($nextMatchday->matchday_number)->toBe(1);
});

test('matchday isCurrentlyActive returns true for unlimited modes when not complete', function () {
    $season = Season::factory()->create([
        'matchday_schedule_mode' => MatchdayScheduleMode::UnlimitedNoOrder,
    ]);
    
    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'deadline_at' => null,
    ]);

    expect($matchday->isCurrentlyActive())->toBeTrue();
});

test('matchday isCurrentlyActive returns false for unlimited modes when complete', function () {
    $season = Season::factory()->create([
        'matchday_schedule_mode' => MatchdayScheduleMode::UnlimitedNoOrder,
    ]);
    
    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'deadline_at' => null,
    ]);

    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();

    MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player1->id,
        'away_player_id' => $player2->id,
        'status' => FixtureStatus::Completed->value,
    ]);

    expect($matchday->isCurrentlyActive())->toBeFalse();
});

test('matchday isUpcoming returns false for unlimited modes', function () {
    $season = Season::factory()->create([
        'matchday_schedule_mode' => MatchdayScheduleMode::UnlimitedNoOrder,
    ]);
    
    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'deadline_at' => null,
    ]);

    expect($matchday->isUpcoming())->toBeFalse();
});
