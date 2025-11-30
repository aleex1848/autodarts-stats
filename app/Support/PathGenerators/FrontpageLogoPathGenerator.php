<?php

namespace App\Support\PathGenerators;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class FrontpageLogoPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return "frontpage-logos/{$media->model->id}/";
    }

    public function getPathForConversions(Media $media): string
    {
        return "frontpage-logos/{$media->model->id}/conversions/";
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return "frontpage-logos/{$media->model->id}/responsive-images/";
    }
}
