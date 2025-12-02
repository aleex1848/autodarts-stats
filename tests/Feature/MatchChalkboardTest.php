<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\Player;
use App\Models\Turn;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate(RoleName::Spieler->value, 'web');
    Role::findOrCreate(RoleName::SuperAdmin->value, 'web');
});

test('match detail page includes chalkboard component', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Spieler->value);

    $match = DartMatch::factory()->create([
        'variant' => 'X01',
        'base_score' => 501,
    ]);

    $player1 = Player::factory()->create(['user_id' => $user->id]);
    $player2 = Player::factory()->create();

    $match->players()->attach($player1->id, ['player_index' => 0]);
    $match->players()->attach($player2->id, ['player_index' => 1]);

    $leg = Leg::factory()->create([
        'match_id' => $match->id,
        'leg_number' => 1,
        'set_number' => 1,
    ]);

    $turn1 = Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player1->id,
        'round_number' => 1,
        'points' => 60,
        'score_after' => 441,
        'busted' => false,
    ]);

    DartThrow::factory()->create([
        'turn_id' => $turn1->id,
        'dart_number' => 1,
        'segment_number' => 20,
        'multiplier' => 1,
        'points' => 20,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('matches.show', $match));

    $response->assertSuccessful();
    $response->assertSee('Kreidetafel-Ansicht');
});

test('admin match detail page includes chalkboard component', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::SuperAdmin->value);

    $match = DartMatch::factory()->create([
        'variant' => 'X01',
        'base_score' => 501,
    ]);

    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();

    $match->players()->attach($player1->id, ['player_index' => 0]);
    $match->players()->attach($player2->id, ['player_index' => 1]);

    $leg = Leg::factory()->create([
        'match_id' => $match->id,
        'leg_number' => 1,
        'set_number' => 1,
    ]);

    $turn1 = Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player1->id,
        'round_number' => 1,
        'points' => 60,
        'score_after' => 441,
        'busted' => false,
    ]);

    DartThrow::factory()->create([
        'turn_id' => $turn1->id,
        'dart_number' => 1,
        'segment_number' => 20,
        'multiplier' => 1,
        'points' => 20,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('matches.show', $match));

    $response->assertSuccessful();
    $response->assertSee('Kreidetafel-Ansicht');
});

test('chalkboard displays turn data correctly', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Spieler->value);

    $match = DartMatch::factory()->create([
        'variant' => 'X01',
        'base_score' => 501,
    ]);

    $player1 = Player::factory()->create(['name' => 'Player One', 'user_id' => $user->id]);
    $player2 = Player::factory()->create(['name' => 'Player Two']);

    $match->players()->attach($player1->id, ['player_index' => 0]);
    $match->players()->attach($player2->id, ['player_index' => 1]);

    $leg = Leg::factory()->create([
        'match_id' => $match->id,
        'leg_number' => 1,
        'set_number' => 1,
    ]);

    // Player 1's turn with triple 20s
    $turn1 = Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player1->id,
        'round_number' => 1,
        'points' => 180,
        'score_after' => 321,
        'busted' => false,
    ]);

    DartThrow::factory()->create([
        'turn_id' => $turn1->id,
        'dart_number' => 1,
        'segment_number' => 20,
        'multiplier' => 3,
        'points' => 60,
    ]);

    DartThrow::factory()->create([
        'turn_id' => $turn1->id,
        'dart_number' => 2,
        'segment_number' => 20,
        'multiplier' => 3,
        'points' => 60,
    ]);

    DartThrow::factory()->create([
        'turn_id' => $turn1->id,
        'dart_number' => 3,
        'segment_number' => 20,
        'multiplier' => 3,
        'points' => 60,
    ]);

    // Player 2's busted turn
    $turn2 = Turn::factory()->create([
        'leg_id' => $leg->id,
        'player_id' => $player2->id,
        'round_number' => 1,
        'points' => 85,
        'score_after' => 501,
        'busted' => true,
    ]);

    DartThrow::factory()->create([
        'turn_id' => $turn2->id,
        'dart_number' => 1,
        'segment_number' => 20,
        'multiplier' => 3,
        'points' => 60,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('matches.show', $match));

    $response->assertSuccessful();
    $response->assertSee('Player One');
    $response->assertSee('Player Two');
    $response->assertSee('180'); // Player 1's score
    $response->assertSee('T20'); // Triple 20
    $response->assertSee('321'); // Remaining score
});
