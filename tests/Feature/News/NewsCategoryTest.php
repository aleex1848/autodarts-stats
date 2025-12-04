<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\NewsCategory;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate(RoleName::Admin->value, 'web');
    Role::findOrCreate(RoleName::SuperAdmin->value, 'web');
});

test('guests cannot access news categories admin', function () {
    $response = $this->get(route('admin.news.categories.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access news categories admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('admin.news.categories.index'));
    $response->assertForbidden();
});

test('admin users can access news categories admin', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $response = $this->get(route('admin.news.categories.index'));
    $response->assertSuccessful();
});

test('admin users can create a news category', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    Volt::test('admin.news.categories.index')
        ->set('name', 'Test Kategorie')
        ->set('slug', 'test-kategorie')
        ->set('description', 'Test Beschreibung')
        ->set('color', 'blue')
        ->call('saveCategory')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('news_categories', [
        'name' => 'Test Kategorie',
        'slug' => 'test-kategorie',
        'description' => 'Test Beschreibung',
        'color' => 'blue',
    ]);
});

test('admin users can update a news category', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $category = NewsCategory::factory()->create();

    Volt::test('admin.news.categories.index')
        ->call('editCategory', $category->id)
        ->set('name', 'Updated Kategorie')
        ->set('description', 'Updated Beschreibung')
        ->call('saveCategory')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('news_categories', [
        'id' => $category->id,
        'name' => 'Updated Kategorie',
        'description' => 'Updated Beschreibung',
    ]);
});

test('admin users can delete a news category without news', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $category = NewsCategory::factory()->create();

    Volt::test('admin.news.categories.index')
        ->call('confirmDelete', $category->id)
        ->call('deleteCategory')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('news_categories', [
        'id' => $category->id,
    ]);
});

test('admin users cannot delete a news category with news', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $category = NewsCategory::factory()->create();
    \App\Models\News::factory()->create(['category_id' => $category->id, 'type' => 'platform', 'created_by_user_id' => $user->id]);

    Volt::test('admin.news.categories.index')
        ->call('confirmDelete', $category->id)
        ->assertHasErrors(['delete']);
});

