<?php

use App\Enums\LeagueMatchFormat;
use App\Enums\LeagueMode;
use App\Enums\LeagueStatus;
use App\Enums\LeagueVariant;
use App\Enums\RoleName;
use App\Models\League;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('admin can create a league', function () {
    Role::findOrCreate(RoleName::Admin->value);
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $this->actingAs($admin);

    $leagueData = [
        'name' => 'Test Liga',
        'description' => 'Eine Test-Liga',
        'max_players' => 16,
        'mode' => LeagueMode::DoubleRound->value,
        'variant' => LeagueVariant::Single501DoubleOut->value,
        'match_format' => LeagueMatchFormat::BestOf3->value,
        'days_per_matchday' => 7,
        'status' => LeagueStatus::Registration->value,
        'created_by_user_id' => $admin->id,
    ];

    $league = League::create($leagueData);

    expect($league)->toBeInstanceOf(League::class);
    expect($league->name)->toBe('Test Liga');
    expect($league->max_players)->toBe(16);
    expect($league->mode)->toBe(LeagueMode::DoubleRound->value);
});

test('league has correct relationships', function () {
    $user = User::factory()->create();
    $league = League::factory()->create([
        'created_by_user_id' => $user->id,
    ]);

    expect($league->creator)->toBeInstanceOf(User::class);
    expect($league->creator->id)->toBe($user->id);
});

test('league can have participants', function () {
    $league = League::factory()->create();

    expect($league->participants)->toBeEmpty();

    $league->participants()->create([
        'player_id' => \App\Models\Player::factory()->create()->id,
    ]);

    $league->refresh();

    expect($league->participants)->toHaveCount(1);
});
