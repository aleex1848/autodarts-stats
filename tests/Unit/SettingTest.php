<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('setting get returns default when key does not exist', function () {
    $value = Setting::get('non.existent.key', 'default-value');

    expect($value)->toBe('default-value');
});

test('setting get returns null when key does not exist and no default provided', function () {
    $value = Setting::get('non.existent.key');

    expect($value)->toBeNull();
});

test('setting set creates new setting', function () {
    Setting::set('test.key', 'test-value');

    $this->assertDatabaseHas('settings', [
        'key' => 'test.key',
        'value' => 'test-value',
    ]);
});

test('setting set updates existing setting', function () {
    Setting::set('test.key', 'initial-value');
    Setting::set('test.key', 'updated-value');

    $this->assertDatabaseHas('settings', [
        'key' => 'test.key',
        'value' => 'updated-value',
    ]);

    $this->assertDatabaseCount('settings', 1);
});

test('setting get returns stored value', function () {
    Setting::set('test.key', 'stored-value');

    $value = Setting::get('test.key');

    expect($value)->toBe('stored-value');
});

test('setting can store integer values', function () {
    Setting::set('test.integer', '42');

    $value = Setting::get('test.integer');

    expect($value)->toBe('42');
});

test('setting can store null values', function () {
    Setting::set('test.null', null);

    $value = Setting::get('test.null');

    expect($value)->toBeNull();
});
