<?php

namespace App\Console\Commands;

use App\Models\FrontpageLogo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportFrontpageLogoImage extends Command
{
    protected $signature = 'frontpage-logo:import {path=storage/frontpage-logo.png}';

    protected $description = 'Importiert das Frontpage-Logo in die Media Library';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("Datei nicht gefunden: {$path}");

            return Command::FAILURE;
        }

        $frontpageLogo = FrontpageLogo::firstOrCreate(
            ['is_active' => true],
            ['name' => 'Frontpage Logo']
        );

        if ($frontpageLogo->hasMedia('frontpage-logo')) {
            $this->warn('Frontpage-Logo existiert bereits. Lösche altes Logo...');
            $frontpageLogo->clearMediaCollection('frontpage-logo');
        }

        $this->info('Importiere Frontpage-Logo...');
        $frontpageLogo->addMedia($path)
            ->withResponsiveImages()
            ->toMediaCollection('frontpage-logo');

        $this->info('Frontpage-Logo erfolgreich importiert!');
        $this->info('Responsive Images werden im Hintergrund generiert.');

        return Command::SUCCESS;
    }
}
