<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Header extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('header')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Responsive images werden automatisch von withResponsiveImages() generiert
        // Keine manuellen Conversions nötig
    }

    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
