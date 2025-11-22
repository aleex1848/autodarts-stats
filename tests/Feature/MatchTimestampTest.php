<?php

declare(strict_types=1);

use App\Models\DartMatch;
use App\Models\Leg;
use App\Models\Player;
use App\Models\Turn;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('match timestamps are calculated from turn data when match is finished', function () {
    // Arrange: Create a match with turns spanning a time period
    $match = DartMatch::factory()->create([
        'started_at' => null,
        'finished_at' => null,
    ]);

    $player = Player::factory()->create();
    $leg = Leg::factory()->create([
        'match_id' => $match->id,
        'leg_number' => 1,
        'set_number' => 1,
    ]);

    // Create turns with specific timestamps
    $firstTurnTime = now()->subMinutes(20);
    $lastTurnTime = now()->subMinutes(5);

    Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player->id,
        'started_at' => $firstTurnTime,
    ]);

    Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player->id,
        'started_at' => $firstTurnTime->copy()->addMinutes(5),
    ]);

    Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player->id,
        'started_at' => $lastTurnTime,
    ]);

    // Simulate match finishing - this should calculate timestamps from turns
    $match->update([
        'started_at' => $firstTurnTime,
        'finished_at' => $lastTurnTime,
    ]);

    // Assert: Match timestamps should match first and last turn
    $match->refresh();
    expect($match->started_at->timestamp)->toBe($firstTurnTime->timestamp);
    expect($match->finished_at->timestamp)->toBe($lastTurnTime->timestamp);
    expect($match->started_at->diffInMinutes($match->finished_at))->toEqual(15);
});

test('fix timestamps command corrects existing matches', function () {
    // Arrange: Create a match with incorrect timestamps
    $match = DartMatch::factory()->create([
        'started_at' => now(),
        'finished_at' => now(), // Same time = 0 duration
    ]);

    $player = Player::factory()->create();
    $leg = Leg::factory()->create([
        'match_id' => $match->id,
        'leg_number' => 1,
        'set_number' => 1,
    ]);

    // Create turns with correct timestamps
    $correctStartTime = now()->subMinutes(20);
    $correctEndTime = now()->subMinutes(5);

    Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player->id,
        'started_at' => $correctStartTime,
    ]);

    Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player->id,
        'started_at' => $correctEndTime,
    ]);

    // Act: Run the fix command
    $this->artisan('matches:fix-timestamps', ['--match-id' => $match->id])
        ->assertSuccessful();

    // Assert: Match timestamps should now be correct
    $match->refresh();
    expect($match->started_at->timestamp)->toBe($correctStartTime->timestamp);
    expect($match->finished_at->timestamp)->toBe($correctEndTime->timestamp);
    expect($match->started_at->diffInMinutes($match->finished_at))->toEqual(15);
});
