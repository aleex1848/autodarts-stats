<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\DartMatch;
use App\Models\Player;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate(RoleName::Spieler->value, 'web');
});

test('ein user sieht nur matches seines players', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Spieler->value);
    $player = Player::factory()->create(['user_id' => $user->id]);
    $otherPlayer = Player::factory()->create();

    $visibleMatch = DartMatch::factory()->create(['autodarts_match_id' => 'VISIBLE-MATCH']);
    $visibleMatch->players()->sync([$player->id => ['player_index' => 0]]);

    $hiddenMatch = DartMatch::factory()->create(['autodarts_match_id' => 'HIDDEN-MATCH']);
    $hiddenMatch->players()->sync([$otherPlayer->id => ['player_index' => 0]]);

    $this->actingAs($user);

    $response = $this->get(route('matches.index'));

    $response->assertOk();
    $response->assertSee(route('matches.show', $visibleMatch));
    $response->assertDontSee(route('matches.show', $hiddenMatch));
});

test('ein user kann ein match sehen an dem er teilgenommen hat', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Spieler->value);
    $player = Player::factory()->create(['user_id' => $user->id]);

    $match = DartMatch::factory()->create();
    $match->players()->sync([$player->id => ['player_index' => 0]]);

    $this->actingAs($user);

    $response = $this->get(route('matches.show', $match));
    $response->assertOk();
    $response->assertSee('Matchübersicht');
});

test('ein user darf matches ohne teilnahme nicht sehen', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Spieler->value);
    Player::factory()->create(['user_id' => $user->id]);

    $foreignMatch = DartMatch::factory()->create();

    $this->actingAs($user);

    $this->get(route('matches.show', $foreignMatch))->assertForbidden();
});

test('nutzer ohne player sehen einen hinweis', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Spieler->value);

    $this->actingAs($user);

    $response = $this->get(route('matches.index'));
    $response->assertOk();
    $response->assertSee('kein Player');
});
