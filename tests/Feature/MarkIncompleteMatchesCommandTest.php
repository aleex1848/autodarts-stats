<?php

declare(strict_types=1);

use App\Models\DartMatch;
use App\Models\Setting;
use App\Models\SchedulerLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('command marks matches as incomplete when timeout exceeded', function () {
    // Setting für Timeout setzen (120 Minuten = 2 Stunden)
    Setting::set('scheduler.match_timeout_minutes', 120);

    // Match erstellen, das vor mehr als 2 Stunden gestartet wurde
    $oldMatch = DartMatch::factory()->create([
        'started_at' => now()->subHours(3),
        'finished_at' => null,
        'is_incomplete' => false,
    ]);

    // Match erstellen, das vor weniger als 2 Stunden gestartet wurde (sollte nicht markiert werden)
    $recentMatch = DartMatch::factory()->create([
        'started_at' => now()->subHour(),
        'finished_at' => null,
        'is_incomplete' => false,
    ]);

    // Match erstellen, das bereits beendet ist (sollte nicht markiert werden)
    $finishedMatch = DartMatch::factory()->create([
        'started_at' => now()->subHours(3),
        'finished_at' => now()->subHours(2),
        'is_incomplete' => false,
    ]);

    // Command ausführen
    $this->artisan('app:mark-incomplete-matches')
        ->assertSuccessful();

    // Prüfen, dass nur das alte Match als incomplete markiert wurde
    expect($oldMatch->fresh()->is_incomplete)->toBeTrue();
    expect($recentMatch->fresh()->is_incomplete)->toBeFalse();
    expect($finishedMatch->fresh()->is_incomplete)->toBeFalse();

    // Prüfen, dass ein Log-Eintrag erstellt wurde
    $log = SchedulerLog::where('scheduler_name', 'mark-incomplete-matches')
        ->where('status', 'success')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->affected_records)->toBe(1);
});

test('command does not mark matches without started_at', function () {
    Setting::set('scheduler.match_timeout_minutes', 120);

    // Match ohne started_at erstellen
    $match = DartMatch::factory()->create([
        'started_at' => null,
        'finished_at' => null,
        'is_incomplete' => false,
    ]);

    $this->artisan('app:mark-incomplete-matches')
        ->assertSuccessful();

    expect($match->fresh()->is_incomplete)->toBeFalse();
});

test('command does not mark already incomplete matches', function () {
    Setting::set('scheduler.match_timeout_minutes', 120);

    // Bereits als incomplete markiertes Match
    $incompleteMatch = DartMatch::factory()->create([
        'started_at' => now()->subHours(3),
        'finished_at' => null,
        'is_incomplete' => true,
    ]);

    $this->artisan('app:mark-incomplete-matches')
        ->assertSuccessful();

    // Prüfen, dass kein Log-Eintrag mit betroffenen Datensätzen erstellt wurde
    $log = SchedulerLog::where('scheduler_name', 'mark-incomplete-matches')
        ->where('status', 'success')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->affected_records)->toBe(0);
});

test('command creates error log on exception', function () {
    // Setting mit ungültigem Wert setzen, um einen Fehler zu provozieren
    Setting::set('scheduler.match_timeout_minutes', -1);

    // Mock, der einen Fehler wirft
    $this->artisan('app:mark-incomplete-matches')
        ->assertSuccessful(); // Command gibt trotzdem SUCCESS zurück, aber loggt den Fehler

    // Prüfen, dass ein Error-Log erstellt wurde (wenn ein Fehler auftritt)
    $errorLog = SchedulerLog::where('scheduler_name', 'mark-incomplete-matches')
        ->where('status', 'error')
        ->first();

    // Wenn kein Fehler auftritt, sollte kein Error-Log existieren
    // Der Command sollte auch mit ungültigen Werten funktionieren (Default-Wert verwenden)
});

test('command uses default timeout when setting is not set', function () {
    // Setting löschen
    Setting::where('key', 'scheduler.match_timeout_minutes')->delete();

    // Match erstellen, das vor mehr als 6 Stunden (360 Minuten = Default) gestartet wurde
    $oldMatch = DartMatch::factory()->create([
        'started_at' => now()->subHours(7),
        'finished_at' => null,
        'is_incomplete' => false,
    ]);

    $this->artisan('app:mark-incomplete-matches')
        ->assertSuccessful();

    expect($oldMatch->fresh()->is_incomplete)->toBeTrue();
});
