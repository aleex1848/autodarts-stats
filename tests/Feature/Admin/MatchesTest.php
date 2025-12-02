<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\DartMatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate(RoleName::Admin->value, 'web');
});

test('ein admin sieht alle matches in der übersicht', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $match = DartMatch::factory()->create([
        'started_at' => Carbon::now()->subHour(),
        'finished_at' => Carbon::now(),
    ]);

    $players = Player::factory()->count(2)->create();
    foreach ($players as $index => $player) {
        $match->players()->syncWithoutDetaching([
            $player->id => [
                'player_index' => $index,
                'legs_won' => $index === 0 ? 3 : 1,
                'sets_won' => $index === 0 ? 1 : 0,
                'final_position' => $index + 1,
                'match_average' => 80 + ($index * 5),
                'checkout_rate' => 45.5 - ($index * 10),
                'total_180s' => $index,
            ],
        ]);
    }
    $match->winner()->associate($players->first())->save();

    $this->actingAs($admin);

    $response = $this->get(route('admin.matches.index'));

    $response->assertOk();
    $response->assertSee($match->autodarts_match_id);
    $response->assertSee($players->first()->name);
});

test('ein admin kann einzelne matches einsehen', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $match = DartMatch::factory()->create();
    $players = Player::factory()->count(2)->create();
    foreach ($players as $index => $player) {
        $match->players()->syncWithoutDetaching([
            $player->id => [
                'player_index' => $index,
                'legs_won' => $index === 0 ? 3 : 1,
                'sets_won' => $index === 0 ? 1 : 0,
                'final_position' => $index + 1,
                'match_average' => 80 + ($index * 5),
                'checkout_rate' => 45.5 - ($index * 10),
                'total_180s' => $index,
            ],
        ]);
    }
    $match->winner()->associate($players->first())->save();

    $this->actingAs($admin);

    $response = $this->get(route('matches.show', $match));
    $response->assertOk();
    $response->assertSee($players->first()->name);
    $response->assertSee('Matchdetails');
});

test('nicht-admins dürfen die admin matches nicht sehen', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('admin.matches.index'))->assertForbidden();
});
