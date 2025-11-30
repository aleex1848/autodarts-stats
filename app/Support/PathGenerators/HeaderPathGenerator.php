<?php

namespace App\Support\PathGenerators;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class HeaderPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return "headers/{$media->model->id}/";
    }

    public function getPathForConversions(Media $media): string
    {
        return "headers/{$media->model->id}/conversions/";
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return "headers/{$media->model->id}/responsive-images/";
    }
}
