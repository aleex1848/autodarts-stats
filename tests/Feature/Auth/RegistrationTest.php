<?php

use App\Enums\RoleName;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'privacy_accepted' => true,
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(RoleName::Spieler->value))->toBeTrue();
});

test('registration fails without privacy policy acceptance', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['privacy_accepted']);
});

test('privacy policy page can be rendered', function () {
    $response = $this->get(route('privacy-policy'));

    $response->assertStatus(200)
        ->assertSee('Datenschutzerklärung');
});