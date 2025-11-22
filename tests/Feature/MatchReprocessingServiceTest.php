<?php

declare(strict_types=1);

use App\Models\DartMatch;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('match fields can be reset to default values without null constraint violation', function () {
    // Create a match with custom values
    $match = DartMatch::factory()->create([
        'base_score' => 701,
        'in_mode' => 'Double',
        'out_mode' => 'Double',
        'bull_mode' => '50',
        'max_rounds' => 30,
        'winner_player_id' => Player::factory()->create()->id,
        'finished_at' => now(),
    ]);

    // Verify initial state
    expect($match->base_score)->toBe(701);
    expect($match->in_mode)->toBe('Double');
    expect($match->winner_player_id)->not->toBeNull();

    // Test the reset logic directly (this is what MatchReprocessingService does)
    // This ensures base_score and other non-nullable fields are set to defaults, not null
    $match->update([
        'winner_player_id' => null,
        'finished_at' => null,
        'base_score' => 501,
        'in_mode' => 'Straight',
        'out_mode' => 'Straight',
        'bull_mode' => '25/50',
        'max_rounds' => 20,
    ]);

    $match->refresh();

    // Verify that fields are reset to default values (not null)
    expect($match->base_score)->toBe(501);
    expect($match->in_mode)->toBe('Straight');
    expect($match->out_mode)->toBe('Straight');
    expect($match->bull_mode)->toBe('25/50');
    expect($match->max_rounds)->toBe(20);
    expect($match->winner_player_id)->toBeNull();
    expect($match->finished_at)->toBeNull();
});
