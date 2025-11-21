<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('downloads');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate(RoleName::Admin->value, 'web');
});

test('guests cannot access admin downloads index', function () {
    $response = $this->get(route('admin.downloads.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access admin downloads index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('admin.downloads.index'));
    $response->assertForbidden();
});

test('admin users can access admin downloads index', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $response = $this->get(route('admin.downloads.index'));
    $response->assertSuccessful();
});

test('admin users can view downloads list', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $category = DownloadCategory::factory()->create();
    $download = Download::factory()->create(['category_id' => $category->id, 'created_by' => $user->id]);

    $response = $this->get(route('admin.downloads.index'));
    $response->assertSuccessful();
    $response->assertSee($download->title);
});

test('admin users can create a download', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $category = DownloadCategory::factory()->create();
    $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

    Volt::test('admin.downloads.create')
        ->set('title', 'Test Download')
        ->set('categoryId', $category->id)
        ->set('description', 'Test Description')
        ->set('isActive', true)
        ->set('file', $file)
        ->call('save')
        ->assertRedirect(route('admin.downloads.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('downloads', [
        'title' => 'Test Download',
        'category_id' => $category->id,
        'description' => 'Test Description',
        'is_active' => true,
        'created_by' => $user->id,
    ]);
});

test('admin users can update a download', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $download = Download::factory()->create(['created_by' => $user->id]);
    $category = DownloadCategory::factory()->create();

    Volt::test('admin.downloads.show', ['download' => $download])
        ->set('title', 'Updated Title')
        ->set('categoryId', $category->id)
        ->set('description', 'Updated Description')
        ->set('isActive', false)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('downloads', [
        'id' => $download->id,
        'title' => 'Updated Title',
        'category_id' => $category->id,
        'description' => 'Updated Description',
        'is_active' => false,
    ]);
});

test('admin users can delete a download', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $download = Download::factory()->create(['created_by' => $user->id]);

    Volt::test('admin.downloads.index')
        ->call('deleteDownload', $download->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('downloads', [
        'id' => $download->id,
    ]);
});

test('admin users can manage download categories', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);
    $this->actingAs($user);

    $response = $this->get(route('admin.download-categories.index'));
    $response->assertSuccessful();

    Volt::test('admin.download-categories.index')
        ->set('name', 'Test Category')
        ->set('description', 'Test Description')
        ->call('saveCategory')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('download_categories', [
        'name' => 'Test Category',
        'description' => 'Test Description',
    ]);
});
