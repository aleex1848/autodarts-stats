<?php

use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\Player;
use App\Models\Turn;
use App\Support\WebhookProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\WebhookClient\Models\WebhookCall;

uses(RefreshDatabase::class);

test('match_state corrects a throw when segment changes', function () {
    // First, create initial throw via throw event
    $player = Player::factory()->create(['autodarts_user_id' => 'player-id']);
    $match = DartMatch::factory()->create(['autodarts_match_id' => 'match-id']);
    $leg = Leg::factory()->create(['match_id' => $match->id, 'leg_number' => 1]);
    $turn = Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player->id,
        'autodarts_turn_id' => 'turn-id',
    ]);

    // Create initial throw - player hit S20
    $initialThrow = DartThrow::create([
        'turn_id' => $turn->id,
        'autodarts_throw_id' => 'throw-1-id',
        'dart_number' => 0,
        'segment_number' => 20,
        'multiplier' => 1,
        'points' => 20,
        'segment_name' => 'S20',
        'is_corrected' => false,
    ]);

    expect($initialThrow->is_corrected)->toBeFalse();

    // Now send match_state with corrected throw (actually was S5)
    $payload = [
        'event' => 'match_state',
        'matchId' => 'match-id',
        'variant' => 'X01',
        'data' => [
            'match' => [
                'id' => 'match-id',
                'type' => 'Online',
                'finished' => false,
                'leg' => 1,
                'settings' => ['baseScore' => 501],
                'players' => [
                    [
                        'userId' => 'player-id',
                        'name' => 'Test Player',
                        'user' => ['country' => 'de'],
                    ],
                ],
                'scores' => [['legs' => 0, 'sets' => 0]],
                'turns' => [
                    [
                        'id' => 'turn-id',
                        'playerId' => 'player-id',
                        'round' => 1,
                        'points' => 5,
                        'score' => 496,
                        'busted' => false,
                        'throws' => [
                            [
                                'id' => 'throw-1-corrected-id', // Different throw ID!
                                'throw' => 0,
                                'segment' => [
                                    'number' => 5,
                                    'multiplier' => 1,
                                    'name' => 'S5',
                                ],
                                'coords' => ['x' => 0, 'y' => 0],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $webhookCall = WebhookCall::create([
        'name' => 'default',
        'url' => 'test',
        'payload' => $payload,
    ]);

    $job = new WebhookProcessing($webhookCall);
    $job->handle();

    // Refresh the initial throw
    $initialThrow->refresh();

    // Old throw should be marked as corrected
    expect($initialThrow->is_corrected)->toBeTrue();
    expect($initialThrow->corrected_at)->not->toBeNull();
    expect($initialThrow->corrected_by_throw_id)->not->toBeNull();

    // New throw should exist
    $correctedThrow = DartThrow::where('autodarts_throw_id', 'throw-1-corrected-id')->first();
    expect($correctedThrow)->not->toBeNull();
    expect($correctedThrow->is_corrected)->toBeFalse();
    expect($correctedThrow->segment_number)->toBe(5);
    expect($correctedThrow->points)->toBe(5);

    // Old throw's corrected_by should point to new throw
    expect($initialThrow->corrected_by_throw_id)->toBe($correctedThrow->id);

    // Total throws should be 2 (original + corrected)
    expect(DartThrow::count())->toBe(2);

    // But only 1 should be not corrected
    expect(DartThrow::notCorrected()->count())->toBe(1);
});

test('notCorrected scope filters out corrected throws', function () {
    $turn = Turn::factory()->create();

    $validThrow1 = DartThrow::factory()->create([
        'turn_id' => $turn->id,
        'is_corrected' => false,
    ]);

    $validThrow2 = DartThrow::factory()->create([
        'turn_id' => $turn->id,
        'is_corrected' => false,
    ]);

    $correctedThrow = DartThrow::factory()->create([
        'turn_id' => $turn->id,
        'is_corrected' => true,
        'corrected_at' => now(),
    ]);

    expect(DartThrow::count())->toBe(3);
    expect(DartThrow::notCorrected()->count())->toBe(2);
    expect(DartThrow::corrected()->count())->toBe(1);

    $notCorrected = DartThrow::notCorrected()->get();
    expect($notCorrected->pluck('id')->toArray())->toMatchArray([
        $validThrow1->id,
        $validThrow2->id,
    ]);
});

test('multiple corrections on same dart position only keep latest', function () {
    $player = Player::factory()->create(['autodarts_user_id' => 'player-id']);
    $match = DartMatch::factory()->create(['autodarts_match_id' => 'match-id']);
    $leg = Leg::factory()->create(['match_id' => $match->id, 'leg_number' => 1]);
    $turn = Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player->id,
        'autodarts_turn_id' => 'turn-id',
    ]);

    // Initial throw: S20
    $throw1 = DartThrow::create([
        'turn_id' => $turn->id,
        'autodarts_throw_id' => 'throw-v1',
        'dart_number' => 0,
        'segment_number' => 20,
        'multiplier' => 1,
        'points' => 20,
        'is_corrected' => false,
    ]);

    // First correction via match_state: Actually S5
    $payload1 = [
        'event' => 'match_state',
        'matchId' => 'match-id',
        'variant' => 'X01',
        'data' => [
            'match' => [
                'id' => 'match-id',
                'type' => 'Online',
                'finished' => false,
                'leg' => 1,
                'settings' => ['baseScore' => 501],
                'players' => [['userId' => 'player-id', 'name' => 'Test', 'user' => ['country' => 'de']]],
                'scores' => [['legs' => 0, 'sets' => 0]],
                'turns' => [
                    [
                        'id' => 'turn-id',
                        'playerId' => 'player-id',
                        'round' => 1,
                        'points' => 5,
                        'throws' => [
                            [
                                'id' => 'throw-v2',
                                'throw' => 0,
                                'segment' => ['number' => 5, 'multiplier' => 1, 'name' => 'S5'],
                                'coords' => ['x' => 0, 'y' => 0],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $webhookCall1 = WebhookCall::create(['name' => 'default', 'url' => 'test', 'payload' => $payload1]);
    (new WebhookProcessing($webhookCall1))->handle();

    // Second correction: Actually it was S1
    $payload2 = [
        'event' => 'match_state',
        'matchId' => 'match-id',
        'variant' => 'X01',
        'data' => [
            'match' => [
                'id' => 'match-id',
                'type' => 'Online',
                'finished' => false,
                'leg' => 1,
                'settings' => ['baseScore' => 501],
                'players' => [['userId' => 'player-id', 'name' => 'Test', 'user' => ['country' => 'de']]],
                'scores' => [['legs' => 0, 'sets' => 0]],
                'turns' => [
                    [
                        'id' => 'turn-id',
                        'playerId' => 'player-id',
                        'round' => 1,
                        'points' => 1,
                        'throws' => [
                            [
                                'id' => 'throw-v3',
                                'throw' => 0,
                                'segment' => ['number' => 1, 'multiplier' => 1, 'name' => 'S1'],
                                'coords' => ['x' => 0, 'y' => 0],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $webhookCall2 = WebhookCall::create(['name' => 'default', 'url' => 'test', 'payload' => $payload2]);
    (new WebhookProcessing($webhookCall2))->handle();

    // Should have 3 total throws
    expect(DartThrow::count())->toBe(3);

    // But only 1 not corrected (the latest)
    expect(DartThrow::notCorrected()->count())->toBe(1);

    // The latest throw should be S1
    $latestThrow = DartThrow::notCorrected()->first();
    expect($latestThrow->segment_number)->toBe(1);
    expect($latestThrow->autodarts_throw_id)->toBe('throw-v3');

    // Both earlier throws should be corrected
    expect(DartThrow::corrected()->count())->toBe(2);
});

test('player statistics use only non-corrected throws', function () {
    $player = Player::factory()->create();
    $match = DartMatch::factory()->create();
    $leg = Leg::factory()->create(['match_id' => $match->id]);
    $turn = Turn::factory()->create(['leg_id' => $leg->id, 'player_id' => $player->id]);

    // Create valid throw: T20 (60 points)
    DartThrow::factory()->create([
        'turn_id' => $turn->id,
        'segment_number' => 20,
        'multiplier' => 3,
        'points' => 60,
        'is_corrected' => false,
    ]);

    // Create corrected throw that should be ignored: S1 (1 point)
    DartThrow::factory()->create([
        'turn_id' => $turn->id,
        'segment_number' => 1,
        'multiplier' => 1,
        'points' => 1,
        'is_corrected' => true,
    ]);

    // Calculate average from player's throws
    $average = $player->throws()->notCorrected()->avg('throws.points');

    expect($average)->toBe(60.0);
    expect($player->throws()->count())->toBe(2);
    expect($player->throws()->notCorrected()->count())->toBe(1);
});
