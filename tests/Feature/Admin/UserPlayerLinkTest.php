<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('admin kann einen player mit einem benutzer verknüpfen', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $user = User::factory()->create();
    $player = Player::factory()->create();

    $admin->assignRole($role);
    $user->assignRole($role);

    $this->actingAs($admin);

    Volt::test('admin.users.index')
        ->call('editUser', $user->id)
        ->set('selectedPlayerId', $player->id)
        ->call('saveUser')
        ->assertHasNoErrors();

    expect($player->fresh()->user_id)->toBe($user->id);
});

test('admin kann eine bestehende player-verknüpfung lösen', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);

    $admin->assignRole($role);
    $user->assignRole($role);

    $this->actingAs($admin);

    Volt::test('admin.users.index')
        ->call('editUser', $user->id)
        ->set('selectedPlayerId', null)
        ->call('saveUser')
        ->assertHasNoErrors();

    expect($player->fresh()->user_id)->toBeNull();
});
