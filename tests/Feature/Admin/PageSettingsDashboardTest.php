<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\SettingsService;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Setting::query()->delete();
});

it('displays default values in dashboard settings form', function () {
    $user = \App\Models\User::factory()->create();
    $user->assignRole('Admin');

    actingAs($user);

    Volt::test('admin.page-settings.index')
        ->assertSet('latestMatchesCount', 5)
        ->assertSet('runningMatchesCount', 5);
});

it('can save dashboard settings', function () {
    $user = \App\Models\User::factory()->create();
    $user->assignRole('Admin');

    actingAs($user);

    Volt::test('admin.page-settings.index')
        ->set('latestMatchesCount', 10)
        ->set('runningMatchesCount', 15)
        ->call('saveDashboardSettings')
        ->assertHasNoErrors();

    expect(SettingsService::getLatestMatchesCount())->toBe(10);
    expect(SettingsService::getRunningMatchesCount())->toBe(15);
});

it('validates dashboard settings values', function () {
    $user = \App\Models\User::factory()->create();
    $user->assignRole('Admin');

    actingAs($user);

    Volt::test('admin.page-settings.index')
        ->set('latestMatchesCount', 0)
        ->set('runningMatchesCount', 101)
        ->call('saveDashboardSettings')
        ->assertHasErrors(['latestMatchesCount', 'runningMatchesCount']);
});

it('loads saved settings on mount', function () {
    $user = \App\Models\User::factory()->create();
    $user->assignRole('Admin');

    SettingsService::setLatestMatchesCount(12);
    SettingsService::setRunningMatchesCount(18);

    actingAs($user);

    Volt::test('admin.page-settings.index')
        ->assertSet('latestMatchesCount', 12)
        ->assertSet('runningMatchesCount', 18);
});
