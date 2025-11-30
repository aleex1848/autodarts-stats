<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Header;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('public');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate(RoleName::Admin->value, 'web');
});

test('guests cannot access admin page settings index', function () {
    $response = $this->get(route('admin.page-settings.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access admin page settings index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('admin.page-settings.index'));
    $response->assertForbidden();
});

test('admin users can access admin page settings index', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $response = $this->get(route('admin.page-settings.index'));
    $response->assertSuccessful();
});

test('admin users can view headers list', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $header = Header::factory()->create();

    $response = $this->get(route('admin.page-settings.index'));
    $response->assertSuccessful();
    $response->assertSee($header->name);
});

test('admin users can create a header', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    Volt::test('admin.page-settings.index')
        ->set('name', 'Test Header')
        ->set('isActive', true)
        ->call('saveHeader')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('headers', [
        'name' => 'Test Header',
        'is_active' => true,
    ]);
});

test('admin users can create a header with logo', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $file = UploadedFile::fake()->image('logo.png', 100, 100);

    Volt::test('admin.page-settings.index')
        ->set('name', 'Test Header with Logo')
        ->set('isActive', true)
        ->set('logo', $file)
        ->call('saveHeader')
        ->assertHasNoErrors();

    $header = Header::where('name', 'Test Header with Logo')->first();
    expect($header)->not->toBeNull();
    expect($header->hasMedia('header'))->toBeTrue();
});

test('admin users can update a header', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $header = Header::factory()->create([
        'name' => 'Original Name',
        'is_active' => false,
    ]);

    Volt::test('admin.page-settings.index')
        ->set('editingHeaderId', $header->id)
        ->set('name', 'Updated Name')
        ->set('isActive', true)
        ->call('saveHeader')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('headers', [
        'id' => $header->id,
        'name' => 'Updated Name',
        'is_active' => true,
    ]);
});

test('admin users can delete a header', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $header = Header::factory()->create();

    Volt::test('admin.page-settings.index')
        ->call('confirmDelete', $header->id)
        ->assertSet('headerIdBeingDeleted', $header->id)
        ->call('deleteHeader')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('headers', [
        'id' => $header->id,
    ]);
});

test('header name is required', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    Volt::test('admin.page-settings.index')
        ->set('name', '')
        ->set('isActive', true)
        ->call('saveHeader')
        ->assertHasErrors(['name']);
});

test('header logo must be an image', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('document.pdf', 100);

    Volt::test('admin.page-settings.index')
        ->set('name', 'Test Header')
        ->set('isActive', true)
        ->set('logo', $file)
        ->call('saveHeader')
        ->assertHasErrors(['logo']);
});
