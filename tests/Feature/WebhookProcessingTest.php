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

test('handleThrow creates all necessary records', function () {
    $matchId = '019a8d99-9fd4-7885-a9fe-2139955793c2';
    $playerId = '019a8d99-a0c2-7cb2-99e0-ffcb91d8bfc9'; // Spiel-spezifische ID

    // NOTE: We do NOT create a match_state webhook here, because we want to test
    // that throw events are processed when no match_state exists
    // The throw event will use the fallback logic (playerId as userId for backwards compatibility)

    // Now create the throw event
    $payload = [
        'event' => 'throw',
        'matchId' => $matchId,
        'data' => [
            'matchId' => $matchId,
            'turnId' => '019a8d99-ae3a-7d85-9a89-af9677e1f49e',
            'playerId' => $playerId, // Spiel-spezifische ID
            'playerName' => 'TestPlayer',
            'leg' => 1,
            'set' => 1,
            'round' => 1,
            'score' => 101,
            'throw' => [
                'id' => '019a8d9a-0689-7843-b956-e5bed41dece6',
                'throw' => 0,
                'segment' => [
                    'number' => 20,
                    'multiplier' => 1,
                    'name' => 'S20',
                    'bed' => 'SingleInner',
                ],
                'coords' => [
                    'x' => -0.006902184245127994,
                    'y' => 0.4054243384459453,
                ],
                'createdAt' => '2025-11-16T16:57:53.034663222Z',
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

    expect(Player::count())->toBe(1);
    expect(DartMatch::count())->toBe(1);
    expect(Leg::count())->toBe(1);
    expect(Turn::count())->toBe(1);
    expect(DartThrow::count())->toBe(1);

    $player = Player::first();
    expect($player->name)->toBe('TestPlayer');
    // When no match_state exists, fallback uses playerId as userId (for backwards compatibility)
    expect($player->autodarts_user_id)->toBe($playerId);

    $throw = DartThrow::first();
    expect($throw->segment_number)->toBe(20);
    expect($throw->multiplier)->toBe(1);
    expect($throw->points)->toBe(20);
    expect($throw->is_corrected)->toBeFalse();
});

test('handleMatchState creates and updates match', function () {
    $payload = [
        'event' => 'match_state',
        'matchId' => '019a8e26-5b7d-7d99-8454-fefde36190e0',
        'variant' => 'X01',
        'data' => [
            'match' => [
                'id' => '019a8e26-5b7d-7d99-8454-fefde36190e0',
                'type' => 'Online',
                'createdAt' => '2025-11-16T19:31:53.983101087Z',
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
                        'userId' => 'player-1-id',
                        'name' => 'Player One',
                        'avatarUrl' => 'https://example.com/avatar1.jpg',
                        'user' => [
                            'country' => 'de',
                        ],
                    ],
                    [
                        'userId' => 'player-2-id',
                        'name' => 'Player Two',
                        'avatarUrl' => 'https://example.com/avatar2.jpg',
                        'user' => [
                            'country' => 'us',
                        ],
                    ],
                ],
                'scores' => [
                    ['legs' => 0, 'sets' => 0],
                    ['legs' => 1, 'sets' => 0],
                ],
                'turns' => [],
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

    expect(DartMatch::count())->toBe(1);
    expect(Player::count())->toBe(2);

    $match = DartMatch::first();
    expect($match->variant)->toBe('X01');
    expect($match->base_score)->toBe(501);
    expect($match->finished_at)->toBeNull();

    $players = Player::all();
    expect($players)->toHaveCount(2);
    expect($match->players)->toHaveCount(2);
});

test('handleMatchState marks match as finished with winner', function () {
    $payload = [
        'event' => 'match_state',
        'matchId' => '019a8e26-5b7d-7d99-8454-fefde36190e0',
        'variant' => 'X01',
        'data' => [
            'match' => [
                'id' => '019a8e26-5b7d-7d99-8454-fefde36190e0',
                'type' => 'Online',
                'createdAt' => '2025-11-16T19:31:53.983101087Z',
                'finished' => true,
                'winner' => 1,
                'settings' => [
                    'baseScore' => 501,
                ],
                'players' => [
                    [
                        'userId' => 'player-1-id',
                        'name' => 'Player One',
                        'user' => ['country' => 'de'],
                    ],
                    [
                        'userId' => 'player-2-id',
                        'name' => 'Player Two',
                        'user' => ['country' => 'us'],
                    ],
                ],
                'scores' => [
                    ['legs' => 0, 'sets' => 0],
                    ['legs' => 2, 'sets' => 1],
                ],
                'turns' => [],
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

    $match = DartMatch::first();
    expect($match->finished_at)->not->toBeNull();
    expect($match->winner_player_id)->not->toBeNull();

    $winner = $match->winner;
    expect($winner->autodarts_user_id)->toBe('player-2-id');
});

test('multiple throw events for same turn accumulate correctly', function () {
    $matchId = '019a8d99-9fd4-7885-a9fe-2139955793c2';
    $turnId = '019a8d99-ae3a-7d85-9a89-af9677e1f49e';
    $playerId = '019a8d99-a0c2-7cb2-99e0-ffcb91d8bfc9'; // Spiel-spezifische ID
    $userId = 'a38b3a08-002a-4551-9ff9-d38e076f2eb8'; // Eindeutige Benutzer-ID

    // NOTE: We do NOT create a match_state webhook here, because we want to test
    // that throw events are processed when no match_state exists
    // The throw events will use the fallback logic to find/create players

    // First throw
    $payload1 = [
        'event' => 'throw',
        'matchId' => $matchId,
        'data' => [
            'turnId' => $turnId,
            'playerId' => $playerId,
            'playerName' => 'TestPlayer',
            'leg' => 1,
            'set' => 1,
            'round' => 1,
            'throw' => [
                'id' => 'throw-1-id',
                'throw' => 0,
                'segment' => ['number' => 20, 'multiplier' => 1, 'name' => 'S20'],
                'coords' => ['x' => 0, 'y' => 0],
            ],
        ],
    ];

    // Second throw
    $payload2 = [
        'event' => 'throw',
        'matchId' => $matchId,
        'data' => [
            'turnId' => $turnId,
            'playerId' => $playerId,
            'playerName' => 'TestPlayer',
            'leg' => 1,
            'set' => 1,
            'round' => 1,
            'throw' => [
                'id' => 'throw-2-id',
                'throw' => 1,
                'segment' => ['number' => 20, 'multiplier' => 3, 'name' => 'T20'],
                'coords' => ['x' => 0, 'y' => 0],
            ],
        ],
    ];

    // Third throw
    $payload3 = [
        'event' => 'throw',
        'matchId' => $matchId,
        'data' => [
            'turnId' => $turnId,
            'playerId' => $playerId,
            'playerName' => 'TestPlayer',
            'leg' => 1,
            'set' => 1,
            'round' => 1,
            'throw' => [
                'id' => 'throw-3-id',
                'throw' => 2,
                'segment' => ['number' => 20, 'multiplier' => 1, 'name' => 'S20'],
                'coords' => ['x' => 0, 'y' => 0],
            ],
        ],
    ];

    foreach ([$payload1, $payload2, $payload3] as $payload) {
        $webhookCall = WebhookCall::create([
            'name' => 'default',
            'url' => 'test',
            'payload' => $payload,
        ]);

        $job = new WebhookProcessing($webhookCall);
        $job->handle();
    }

    expect(Turn::count())->toBe(1);
    expect(DartThrow::count())->toBe(3);

    $turn = Turn::first();
    expect($turn->throws)->toHaveCount(3);

    $throws = $turn->throws->sortBy('dart_number');
    expect($throws->first()->segment_number)->toBe(20);
    expect($throws->first()->multiplier)->toBe(1);
    expect($throws->skip(1)->first()->multiplier)->toBe(3);
});

test('handleThrow can handle negative points from Bull-Out', function () {
    $matchId = '019a8d99-9fd4-7885-a9fe-2139955793c2';
    $playerId = '019a8d99-a0c2-7cb2-99e0-ffcb91d8bfc9'; // Spiel-spezifische ID
    $userId = 'a38b3a08-002a-4551-9ff9-d38e076f2eb8'; // Eindeutige Benutzer-ID

    // NOTE: We do NOT create a match_state webhook here, because we want to test
    // that throw events are processed when no match_state exists

    $payload = [
        'event' => 'throw',
        'matchId' => $matchId,
        'data' => [
            'matchId' => $matchId,
            'turnId' => '019a8d99-ae3a-7d85-9a89-af9677e1f49e',
            'playerId' => $playerId,
            'playerName' => 'TestPlayer',
            'leg' => 1,
            'set' => 1,
            'round' => 2, // Round 2, not round 1, so it's not a Bull-Off
            'score' => -6, // Negative score from Bull-Out miss
            'throw' => [
                'id' => '019a8d9a-0689-7843-b956-e5bed41dece6',
                'throw' => 0,
                'segment' => [
                    'number' => 25,
                    'multiplier' => 1,
                    'name' => 'S25',
                    'bed' => 'SingleOuter',
                ],
                'coords' => [
                    'x' => -0.006902184245127994,
                    'y' => 0.4054243384459453,
                ],
                'createdAt' => '2025-11-16T16:57:53.034663222Z',
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

    $turn = Turn::first();
    expect($turn)->not->toBeNull();
    expect($turn->points)->toBe(-6); // Negative points should be allowed
});
