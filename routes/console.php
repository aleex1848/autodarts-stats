<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler für unvollständige Matches
Schedule::command('app:mark-incomplete-matches')
    ->everyMinute()
    ->when(function () {
        $intervalMinutes = (int) Setting::get('scheduler.interval_minutes', 60);
        
        if ($intervalMinutes <= 0) {
            return false;
        }
        
        // Prüfe, ob genug Zeit seit letztem Lauf vergangen ist
        $lastRunKey = 'scheduler.mark-incomplete-matches.last_run';
        $lastRun = cache()->get($lastRunKey);
        
        if ($lastRun === null) {
            cache()->put($lastRunKey, now()->timestamp, now()->addDays(1));
            
            return true;
        }
        
        $lastRunTime = \Carbon\Carbon::createFromTimestamp($lastRun);
        $nextRun = $lastRunTime->copy()->addMinutes($intervalMinutes);
        
        if (now()->gte($nextRun)) {
            cache()->put($lastRunKey, now()->timestamp, now()->addDays(1));
            
            return true;
        }
        
        return false;
    });

// Model Pruning für SchedulerLogs
Schedule::command('model:prune', [
    '--model' => [\App\Models\SchedulerLog::class],
])->daily();
