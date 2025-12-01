<?php

namespace App\Console\Commands;

use App\Models\DartMatch;
use App\Models\Setting;
use App\Models\SchedulerLog;
use Illuminate\Console\Command;

class MarkIncompleteMatchesCommand extends Command
{
    protected $signature = 'app:mark-incomplete-matches';

    protected $description = 'Markiert Matches als unvollständig, die nicht beendet wurden';

    public function handle(): int
    {
        $this->info('Suche nach unvollständigen Matches...');

        try {
            // Timeout aus Settings lesen (Default: 360 Minuten = 6 Stunden)
            $timeoutMinutes = (int) Setting::get('scheduler.match_timeout_minutes', 360);
            $cutoffTime = now()->subMinutes($timeoutMinutes);

            // Matches finden: started_at < cutoffTime UND finished_at = null UND is_incomplete = false
            $matches = DartMatch::query()
                ->whereNotNull('started_at')
                ->where('started_at', '<', $cutoffTime)
                ->whereNull('finished_at')
                ->where('is_incomplete', false)
                ->get();

            $count = $matches->count();

            if ($count === 0) {
                $this->info('Keine unvollständigen Matches gefunden.');

                SchedulerLog::create([
                    'scheduler_name' => 'mark-incomplete-matches',
                    'status' => 'success',
                    'message' => 'Keine unvollständigen Matches gefunden.',
                    'affected_records' => 0,
                    'executed_at' => now(),
                ]);

                return self::SUCCESS;
            }

            $this->info("Gefunden: {$count} unvollständige Matches");

            // Matches als incomplete markieren
            $updated = 0;
            foreach ($matches as $match) {
                $match->update(['is_incomplete' => true]);
                $updated++;
            }

            $this->info("✓ {$updated} Matches wurden als unvollständig markiert.");

            // Log erstellen
            SchedulerLog::create([
                'scheduler_name' => 'mark-incomplete-matches',
                'status' => 'success',
                'message' => "{$updated} Matches wurden als unvollständig markiert.",
                'affected_records' => $updated,
                'executed_at' => now(),
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Fehler: {$e->getMessage()}");

            // Fehler-Log erstellen
            SchedulerLog::create([
                'scheduler_name' => 'mark-incomplete-matches',
                'status' => 'error',
                'message' => $e->getMessage(),
                'affected_records' => 0,
                'executed_at' => now(),
            ]);

            return self::FAILURE;
        }
    }
}
