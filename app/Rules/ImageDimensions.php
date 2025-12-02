<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ImageDimensions implements ValidationRule
{
    protected ?int $width = null;
    protected ?int $height = null;
    protected bool $square = false;

    public function __construct(?int $width = null, ?int $height = null, bool $square = false)
    {
        $this->width = $width;
        $this->height = $height;
        $this->square = $square;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value) {
            return;
        }

        try {
            $image = getimagesize($value->getRealPath());
            
            if (!$image) {
                $fail("Das Bild konnte nicht verarbeitet werden.");
                return;
            }

            [$width, $height] = $image;

            // Prüfe auf Quadrat
            if ($this->square && $width !== $height) {
                $fail("Das Logo muss quadratisch sein (Breite und Höhe müssen gleich sein). Aktuell: {$width}x{$height}px");
                return;
            }

            // Prüfe auf spezifische Dimensionen
            if ($this->width !== null && $width !== $this->width) {
                $fail("Das Bild muss eine Breite von {$this->width}px haben. Aktuell: {$width}px");
                return;
            }

            if ($this->height !== null && $height !== $this->height) {
                $fail("Das Bild muss eine Höhe von {$this->height}px haben. Aktuell: {$height}px");
                return;
            }
        } catch (\Exception $e) {
            $fail("Fehler beim Verarbeiten des Bildes: " . $e->getMessage());
        }
    }
}
