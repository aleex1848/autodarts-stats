<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @use HasFactory<\Database\Factories\DownloadFactory> */
class Download extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DownloadCategory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registerMediaCollections(): void
    {
        $mimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'application/x-rar-compressed',
            'application/octet-stream',
            'text/plain',
            'image/jpeg',
            'image/png',
            'image/gif',
            'video/mp4',
            'video/quicktime',
        ];

        // Allow empty files in testing environment
        if (app()->environment('testing')) {
            $mimeTypes[] = 'application/x-empty';
        }

        $this->addMediaCollection('files')
            ->singleFile()
            ->useDisk('downloads')
            ->acceptsMimeTypes($mimeTypes);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->performOnCollections('files')
            ->onlyForMimeTypes(['image/jpeg', 'image/png', 'image/gif']);
    }
}
