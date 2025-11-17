<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\WebhookClient\Models\WebhookCall;

class RecoverWebhooksFromLogs extends Command
{
    protected $signature = 'webhooks:recover-from-logs 
                            {--log-file=storage/logs/laravel.log : Pfad zur Log-Datei}
                            {--dry-run : Zeige nur was wiederhergestellt werden würde}';

    protected $description = 'Recover webhook calls from Laravel logs after accidental data loss';

    public function handle(): int
    {
        $logFile = base_path($this->option('log-file'));

        if (! file_exists($logFile)) {
            $this->error("Log-Datei nicht gefunden: {$logFile}");

            return self::FAILURE;
        }

        $this->info('Durchsuche Laravel Logs nach WebhookCall-Einträgen...');
        $this->newLine();

        $content = file_get_contents($logFile);
        $lines = explode("\n", $content);

        $webhooks = [];
        $currentEntry = null;

        foreach ($lines as $line) {
            // Suche nach "WebhookCall created" Einträgen
            if (str_contains($line, 'WebhookCall created')) {
                // Extract JSON from log line
                if (preg_match('/WebhookCall created\s+(.+)$/', $line, $matches)) {
                    $jsonData = $matches[1];

                    try {
                        $data = json_decode($jsonData, true);

                        if (isset($data['payload'])) {
                            $webhooks[] = [
                                'name' => $data['name'] ?? 'default',
                                'url' => $data['url'] ?? 'recovered',
                                'payload' => $data['payload'],
                                'headers' => null,
                            ];
                        }
                    } catch (\Exception $e) {
                        // Skip invalid JSON
                    }
                }
            }
        }

        $total = count($webhooks);

        if ($total === 0) {
            $this->warn('Keine WebhookCall-Einträge in den Logs gefunden.');

            return self::SUCCESS;
        }

        $this->info("Gefunden: {$total} WebhookCall-Einträge!");
        $this->newLine();

        // Gruppiere nach Event-Typ
        $eventCounts = collect($webhooks)->groupBy(fn ($w) => $w['payload']['event'] ?? 'unknown')->map->count();

        $this->line('Event-Verteilung:');
        foreach ($eventCounts as $event => $count) {
            $this->line("  - {$event}: {$count}");
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - Keine Daten werden wiederhergestellt!');

            return self::SUCCESS;
        }

        if (! $this->confirm('Möchtest du diese WebhookCalls wiederherstellen?', true)) {
            $this->info('Abgebrochen.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $created = 0;
        $skipped = 0;

        foreach ($webhooks as $webhookData) {
            try {
                // Prüfe ob dieser Webhook bereits existiert (anhand payload hash)
                $payloadHash = md5(json_encode($webhookData['payload']));

                $exists = WebhookCall::where(function ($query) use ($webhookData) {
                    $query->whereRaw("MD5(JSON_EXTRACT(payload, '$')) = ?", [md5(json_encode($webhookData['payload']))]);
                })->exists();

                if (! $exists) {
                    WebhookCall::create($webhookData);
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Fehler beim Erstellen: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Erfolgreich wiederhergestellt: {$created}");

        if ($skipped > 0) {
            $this->line("⊘ Übersprungen (bereits vorhanden): {$skipped}");
        }

        $this->newLine();
        $this->info('🎉 Datenwiederherstellung abgeschlossen!');
        $this->line('Führe jetzt aus: php artisan webhooks:reprocess');

        return self::SUCCESS;
    }
}
