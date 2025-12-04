<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\News;
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

test('guests cannot access platform news admin', function () {
    $response = $this->get(route('admin.news.platform.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access platform news admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('admin.news.platform.index'));
    $response->assertForbidden();
});

test('admin users can access platform news admin', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $response = $this->get(route('admin.news.platform.index'));
    $response->assertSuccessful();
});

test('admin users can create platform news', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $category = NewsCategory::factory()->create();

    Volt::test('admin.news.platform.index')
        ->set('title', 'Test News')
        ->set('content', '<p>Test Content</p>')
        ->set('excerpt', 'Test Excerpt')
        ->set('categoryId', $category->id)
        ->set('isPublished', true)
        ->call('saveNews')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('news', [
        'type' => 'platform',
        'title' => 'Test News',
        'content' => '<p>Test Content</p>',
        'excerpt' => 'Test Excerpt',
        'category_id' => $category->id,
        'is_published' => true,
        'created_by_user_id' => $user->id,
    ]);
});

test('admin users can update platform news', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $news = News::factory()->platform()->create(['created_by_user_id' => $user->id]);
    $category = NewsCategory::factory()->create();

    Volt::test('admin.news.platform.index')
        ->call('editNews', $news->id)
        ->set('title', 'Updated News')
        ->set('content', '<p>Updated Content</p>')
        ->set('categoryId', $category->id)
        ->call('saveNews')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('news', [
        'id' => $news->id,
        'title' => 'Updated News',
        'content' => '<p>Updated Content</p>',
        'category_id' => $category->id,
    ]);
});

test('admin users can delete platform news', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $news = News::factory()->platform()->create(['created_by_user_id' => $user->id]);

    Volt::test('admin.news.platform.index')
        ->call('confirmDelete', $news->id)
        ->call('deleteNews')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('news', [
        'id' => $news->id,
    ]);
});

test('platform news slug is auto-generated from title', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    Volt::test('admin.news.platform.index')
        ->set('title', 'Test News Title')
        ->set('content', '<p>Content</p>')
        ->call('saveNews')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('news', [
        'title' => 'Test News Title',
        'slug' => 'test-news-title',
    ]);
});

