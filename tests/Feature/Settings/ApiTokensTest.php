<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Volt\Volt;

test('api tokens page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/api-tokens')
        ->assertSuccessful()
        ->assertSee('API Tokens');
});

test('user can create an api token', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('settings.api-tokens')
        ->set('tokenName', 'Test Token')
        ->call('createToken')
        ->assertHasNoErrors();

    expect($user->tokens()->where('name', 'Test Token')->exists())->toBeTrue();
});

test('token name is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('settings.api-tokens')
        ->set('tokenName', '')
        ->call('createToken')
        ->assertHasErrors(['tokenName' => 'required']);
});

test('user can see their api tokens', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Token');

    $this->actingAs($user);

    Volt::test('settings.api-tokens')
        ->assertSee('Test Token');
});

test('user can delete an api token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Token');

    $this->actingAs($user);

    Volt::test('settings.api-tokens')
        ->call('deleteToken', $token->accessToken->id)
        ->assertHasNoErrors();

    expect($user->tokens()->where('id', $token->accessToken->id)->exists())->toBeFalse();
});

test('plain text token is shown after creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('settings.api-tokens')
        ->set('tokenName', 'Test Token')
        ->call('createToken');

    expect($component->get('plainTextToken'))->not->toBeNull();
});

test('plain text token can be closed', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('settings.api-tokens')
        ->set('tokenName', 'Test Token')
        ->call('createToken')
        ->call('closeTokenModal');

    expect($component->get('plainTextToken'))->toBeNull();
});

test('user cannot see other users tokens', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->createToken('User 1 Token');

    $this->actingAs($user2);

    Volt::test('settings.api-tokens')
        ->assertDontSee('User 1 Token');
});
