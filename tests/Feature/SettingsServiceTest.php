<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\SettingsService;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    Setting::query()->delete();
});

it('returns default value for latest matches count when not set', function () {
    expect(SettingsService::getLatestMatchesCount())->toBe(5);
});

it('returns default value for running matches count when not set', function () {
    expect(SettingsService::getRunningMatchesCount())->toBe(5);
});

it('can set and get latest matches count', function () {
    SettingsService::setLatestMatchesCount(10);

    expect(SettingsService::getLatestMatchesCount())->toBe(10);
    assertDatabaseHas('settings', [
        'key' => 'dashboard.latest_matches_count',
        'value' => '10',
    ]);
});

it('can set and get running matches count', function () {
    SettingsService::setRunningMatchesCount(15);

    expect(SettingsService::getRunningMatchesCount())->toBe(15);
    assertDatabaseHas('settings', [
        'key' => 'dashboard.running_matches_count',
        'value' => '15',
    ]);
});

it('updates existing setting when setting again', function () {
    SettingsService::setLatestMatchesCount(5);
    SettingsService::setLatestMatchesCount(20);

    expect(SettingsService::getLatestMatchesCount())->toBe(20);
    expect(Setting::where('key', 'dashboard.latest_matches_count')->count())->toBe(1);
});
