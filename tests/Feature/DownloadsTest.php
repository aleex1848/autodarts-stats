<?php

declare(strict_types=1);

use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('downloads');
});

test('authenticated users can view download detail page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = DownloadCategory::factory()->create();
    $download = Download::factory()->active()->create(['category_id' => $category->id]);

    $file = UploadedFile::fake()->create('test.pdf', 100);
    $download->addMedia($file)->toMediaCollection('files');

    $response = $this->get(route('downloads.show', $download));
    $response->assertSuccessful();
    $response->assertSee($download->title);
});

test('users can download a file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $download = Download::factory()->active()->create();
    $file = UploadedFile::fake()->create('test.pdf', 100);
    $media = $download->addMedia($file)->toMediaCollection('files');

    $response = $this->get(route('downloads.file', $download));
    $response->assertSuccessful();
    $response->assertDownload($media->file_name);
});

test('download detail page shows file information', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $download = Download::factory()->active()->create();
    $file = UploadedFile::fake()->create('test-document.pdf', 100);
    $media = $download->addMedia($file)->toMediaCollection('files');

    $response = $this->get(route('downloads.show', $download));
    $response->assertSuccessful();
    $response->assertSee($media->file_name);
    $response->assertSee($media->mime_type);
});

test('dashboard shows latest downloads', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $download1 = Download::factory()->active()->create(['created_at' => now()->subDay()]);
    $download2 = Download::factory()->active()->create(['created_at' => now()]);

    $response = $this->get(route('dashboard'));
    $response->assertSuccessful();
    $response->assertSee($download2->title);
});
