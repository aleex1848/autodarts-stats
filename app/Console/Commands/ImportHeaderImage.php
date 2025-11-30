<?php

namespace App\Console\Commands;

use App\Models\Header;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportHeaderImage extends Command
{
    protected $signature = 'header:import {path=storage/header.png}';

    protected $description = 'Importiert das Header-Bild in die Media Library';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("Datei nicht gefunden: {$path}");

            return Command::FAILURE;
        }

        $header = Header::firstOrCreate(
            ['is_active' => true],
            ['name' => 'Header']
        );

        if ($header->hasMedia('header')) {
            $this->warn('Header-Bild existiert bereits. Lösche altes Bild...');
            $header->clearMediaCollection('header');
        }

        $this->info('Importiere Header-Bild...');
        $header->addMedia($path)
            ->withResponsiveImages()
            ->toMediaCollection('header');

        $this->info('Header-Bild erfolgreich importiert!');
        $this->info('Responsive Images werden im Hintergrund generiert.');

        return Command::SUCCESS;
    }
}
