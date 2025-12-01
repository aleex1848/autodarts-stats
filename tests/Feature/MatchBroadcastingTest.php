<?php

declare(strict_types=1);

use App\Events\MatchUpdated;
use App\Models\DartMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

test('match updated event is broadcasted when match is updated', function () {
    Event::fake([MatchUpdated::class]);

    $match = DartMatch::factory()->create([
        'finished_at' => null,
    ]);

    $match->update([
        'finished_at' => now(),
    ]);

    Event::assertDispatched(MatchUpdated::class, function ($event) use ($match) {
        return $event->match->id === $match->id;
    });
});

test('match updated event broadcasts on correct channels', function () {
    $match = DartMatch::factory()->create();

    $event = new MatchUpdated($match);
    $channels = $event->broadcastOn();

    expect($channels)->toBeArray()
        ->and($channels)->toHaveCount(2)
        ->and($channels[0]->name)->toBe("match.{$match->id}")
        ->and($channels[1]->name)->toBe('matches');
});

test('match updated event broadcasts with correct event name', function () {
    $match = DartMatch::factory()->create();

    $event = new MatchUpdated($match);

    expect($event->broadcastAs())->toBe('match.updated');
});

test('match updated event includes match data in broadcast', function () {
    $match = DartMatch::factory()->create([
        'autodarts_match_id' => 'test-match-123',
    ]);

    $event = new MatchUpdated($match);
    $broadcastData = $event->broadcastWith();

    expect($broadcastData)->toHaveKey('match_id')
        ->and($broadcastData['match_id'])->toBe($match->id)
        ->and($broadcastData)->toHaveKey('autodarts_match_id')
        ->and($broadcastData['autodarts_match_id'])->toBe('test-match-123');
});

test('match channel authorization allows authenticated user with view permission', function () {
    $user = User::factory()->create();
    $match = DartMatch::factory()->create();

    // Teste die Channel-Autorisierung direkt
    $authorized = Broadcast::channel('match.'.$match->id, $user, $match->id);

    // Die Channel-Autorisierung sollte true zurückgeben, wenn der User das Match sehen darf
    expect($authorized)->toBeTrue();
});

test('match channel authorization denies access for non-existent match', function () {
    $user = User::factory()->create();
    $nonExistentMatchId = 99999;

    // Teste die Channel-Autorisierung für ein nicht existierendes Match
    $authorized = Broadcast::channel('match.'.$nonExistentMatchId, $user, $nonExistentMatchId);

    expect($authorized)->toBeFalse();
});
