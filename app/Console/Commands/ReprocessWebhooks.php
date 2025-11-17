<?php

namespace App\Console\Commands;

use App\Support\WebhookProcessing;
use Illuminate\Console\Command;
use Spatie\WebhookClient\Models\WebhookCall;

class ReprocessWebhooks extends Command
{
    protected $signature = 'webhooks:reprocess 
                            {--match-id= : Nur Webhooks für eine bestimmte Match-ID}
                            {--from-date= : Nur Webhooks ab diesem Datum (Y-m-d H:i:s)}
                            {--event= : Nur bestimmten Event-Typ (throw oder match_state)}
                            {--limit= : Maximale Anzahl zu verarbeiten}
                            {--dry-run : Zeige nur was verarbeitet werden würde, ohne es zu verarbeiten}';

    protected $description = 'Reprocess existing webhook calls to populate the statistics database';

    public function handle(): int
    {
        $query = WebhookCall::query()->orderBy('created_at');

        // Filter nach Match-ID
        if ($matchId = $this->option('match-id')) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.matchId')) = ?", [$matchId]);
        }

        // Filter nach Datum
        if ($fromDate = $this->option('from-date')) {
            $query->where('created_at', '>=', $fromDate);
        }

        // Filter nach Event-Typ
        if ($event = $this->option('event')) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = ?", [$event]);
        }

        // Limit
        if ($limit = $this->option('limit')) {
            $query->limit($limit);
        }

        $webhooks = $query->get();
        $total = $webhooks->count();

        if ($total === 0) {
            $this->info('Keine Webhooks zum Verarbeiten gefunden.');

            return self::SUCCESS;
        }

        $this->info("Verarbeite {$total} Webhook Calls...");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - Keine Daten werden verarbeitet!');
            $this->newLine();

            $eventCounts = $webhooks->groupBy(fn ($w) => $w->payload['event'] ?? 'unknown');
            foreach ($eventCounts as $event => $items) {
                $this->line("  - {$event}: {$items->count()}");
            }

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $errors = 0;
        $errorDetails = [];

        foreach ($webhooks as $webhook) {
            try {
                $job = new WebhookProcessing($webhook);
                $job->handle();
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $errorDetails[] = [
                    'id' => $webhook->id,
                    'event' => $webhook->payload['event'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Erfolgreich verarbeitet: {$processed}");

        if ($errors > 0) {
            $this->warn("✗ Fehler: {$errors}");
            $this->newLine();

            if ($this->confirm('Fehlerdetails anzeigen?', true)) {
                $this->table(
                    ['Webhook ID', 'Event', 'Fehler'],
                    array_map(fn ($err) => [$err['id'], $err['event'], $err['error']], $errorDetails)
                );
            }
        }

        return self::SUCCESS;
    }
}
