<?php

declare(strict_types=1);

use App\Events\MatchdayGameStarted;
use App\Models\DartMatch;
use App\Models\Matchday;
use App\Models\MatchdayFixture;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonParticipant;
use App\Models\User;
use App\Support\WebhookProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use Spatie\WebhookClient\Models\WebhookCall;

uses(RefreshDatabase::class);

test('user can start playing a matchday', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $season = Season::factory()->create([
        'status' => 'active',
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
        ->call('startMatchday', $matchday->id);

    $user->refresh();
    expect($user->playing_matchday_id)->toBe($matchday->id);
});

test('user cannot start matchday if not participant', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $season = Season::factory()->create([
        'status' => 'active',
        'days_per_matchday' => 7,
    ]);

    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
        'deadline_at' => now()->addDays(3),
    ]);

    $this->actingAs($user);

    Volt::test('quick-start-matchday')
        ->call('startMatchday', $matchday->id)
        ->assertHasErrors('matchday');
});

test('user can stop playing a matchday', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $season = Season::factory()->create([
        'status' => 'active',
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

    $user->update(['playing_matchday_id' => $matchday->id]);

    $this->actingAs($user);

    Volt::test('quick-start-matchday')
        ->call('stopMatchday');

    $user->refresh();
    expect($user->playing_matchday_id)->toBeNull();
});

test('matchday assignment via webhook links match to fixture', function () {
    Event::fake();

    $user = User::factory()->create();
    $player = Player::factory()->create([
        'user_id' => $user->id,
        'autodarts_user_id' => 'test-user-id',
        'name' => 'TestPlayer',
    ]);
    
    $season = Season::factory()->create([
        'status' => 'active',
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

    $opponentPlayer = Player::factory()->create([
        'autodarts_user_id' => 'opponent-id',
        'name' => 'Opponent',
    ]);

    $fixture = MatchdayFixture::factory()->create([
        'matchday_id' => $matchday->id,
        'home_player_id' => $player->id,
        'away_player_id' => $opponentPlayer->id,
        'status' => 'scheduled',
    ]);

    $user->update(['playing_matchday_id' => $matchday->id]);

    $payload = [
        'event' => 'match_state',
        'matchId' => 'test-match-id',
        'variant' => 'X01',
        'data' => [
            'match' => [
                'id' => 'test-match-id',
                'type' => 'Online',
                'createdAt' => now()->toIso8601String(),
                'finished' => false,
                'settings' => [
                    'baseScore' => 501,
                    'inMode' => 'Straight',
                    'outMode' => 'Straight',
                    'bullMode' => '25/50',
                    'maxRounds' => 20,
                ],
                'players' => [
                    [
                        'userId' => 'test-user-id',
                        'name' => 'TestPlayer',
                    ],
                    [
                        'userId' => 'opponent-id',
                        'name' => 'Opponent',
                    ],
                ],
                'scores' => [
                    ['legs' => 0, 'sets' => 0],
                    ['legs' => 0, 'sets' => 0],
                ],
            ],
        ],
    ];

    $webhookCall = WebhookCall::create([
        'name' => 'default',
        'url' => 'test',
        'payload' => $payload,
        'headers' => [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ],
    ]);

    $job = new WebhookProcessing($webhookCall);
    $job->handle();

    $fixture->refresh();
    expect($fixture->dart_match_id)->not->toBeNull();
    expect($fixture->status)->toBe('completed');

    $user->refresh();
    expect($user->playing_matchday_id)->toBeNull();

    Event::assertDispatched(MatchdayGameStarted::class, function ($event) use ($user, $matchday) {
        return $event->user->id === $user->id
            && $event->matchday->id === $matchday->id
            && $event->success === true;
    });
});

test('matchday isCurrentlyActive returns true when between start and deadline', function () {
    $season = Season::factory()->create([
        'days_per_matchday' => 7,
    ]);

    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
        'deadline_at' => now()->addDays(3),
    ]);

    expect($matchday->isCurrentlyActive())->toBeTrue();
});

test('matchday isUpcoming returns true when start date is in future', function () {
    $season = Season::factory()->create([
        'days_per_matchday' => 7,
    ]);

    $matchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
        'deadline_at' => now()->addDays(10),
    ]);

    expect($matchday->isUpcoming())->toBeTrue();
});

test('season getNextRelevantMatchday returns first active or upcoming matchday', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    
    $season = Season::factory()->create([
        'status' => 'active',
        'days_per_matchday' => 7,
    ]);
    
    SeasonParticipant::factory()->create([
        'season_id' => $season->id,
        'player_id' => $player->id,
    ]);

    $pastMatchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 1,
        'deadline_at' => now()->subDays(5),
    ]);

    $activeMatchday = Matchday::factory()->create([
        'season_id' => $season->id,
        'matchday_number' => 2,
        'deadline_at' => now()->addDays(3),
    ]);

    $nextMatchday = $season->getNextRelevantMatchday($user);

    expect($nextMatchday)->not->toBeNull();
    expect($nextMatchday->id)->toBe($activeMatchday->id);
});

test('season getNextRelevantMatchday returns null for completed season', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    
    $season = Season::factory()->create([
        'status' => 'completed',
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

    $nextMatchday = $season->getNextRelevantMatchday($user);

    expect($nextMatchday)->toBeNull();
});
