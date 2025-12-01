<?php

use App\Models\Setting;
use App\Models\SchedulerLog;
use Illuminate\Support\Facades\Artisan;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // Settings
    public int $matchTimeoutMinutes = 360;
    public int $intervalMinutes = 60;
    public int $logRetentionDays = 30;

    public function mount(): void
    {
        $this->matchTimeoutMinutes = (int) Setting::get('scheduler.match_timeout_minutes', 360);
        $this->intervalMinutes = (int) Setting::get('scheduler.interval_minutes', 60);
        $this->logRetentionDays = (int) Setting::get('scheduler.log_retention_days', 30);
    }

    public function with(): array
    {
        return [
            'logs' => SchedulerLog::query()
                ->orderByDesc('executed_at')
                ->paginate(20),
        ];
    }

    protected function rules(): array
    {
        return [
            'matchTimeoutMinutes' => ['required', 'integer', 'min:1', 'max:10080'], // Max 1 Woche (10080 Minuten)
            'intervalMinutes' => ['required', 'integer', 'min:1', 'max:1440'], // Max 24 Stunden
            'logRetentionDays' => ['required', 'integer', 'min:1', 'max:365'], // Max 1 Jahr
        ];
    }

    public function saveSettings(): void
    {
        $validated = $this->validate();

        Setting::set('scheduler.match_timeout_minutes', $validated['matchTimeoutMinutes']);
        Setting::set('scheduler.interval_minutes', $validated['intervalMinutes']);
        Setting::set('scheduler.log_retention_days', $validated['logRetentionDays']);

        // Cache für Scheduler-Intervall zurücksetzen
        cache()->forget('scheduler.mark-incomplete-matches.last_run');

        $this->dispatch('notify', title: __('Einstellungen gespeichert'));
    }

    public function runCommand(): void
    {
        try {
            Artisan::call('app:mark-incomplete-matches');
            
            $this->dispatch('notify', title: __('Command ausgeführt'), description: __('Der Scheduler wurde manuell ausgeführt.'));
            
            // Zur ersten Seite zurücksetzen und Komponente neu laden, um die aktualisierten Logs anzuzeigen
            $this->resetPage();
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $this->dispatch('notify', 
                title: __('Fehler'), 
                description: __('Fehler beim Ausführen des Commands: :message', ['message' => $e->getMessage()]),
                variant: 'danger'
            );
        }
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Scheduler') }}</flux:heading>
        <flux:subheading>{{ __('Verwalte Scheduler-Einstellungen und Logs') }}</flux:subheading>
    </div>

    <x-page-settings.layout :heading="__('Scheduler Einstellungen')" :subheading="__('Konfiguriere die Scheduler-Intervalle und Log-Aufbewahrung')">
        <div class="space-y-6">
            <!-- Einstellungen-Formular -->
            <form wire:submit="saveSettings" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Match-Timeout (Minuten)') }}</flux:label>
                    <flux:input wire:model="matchTimeoutMinutes" type="number" min="1" max="10080" required />
                    <flux:description>
                        {{ __('Nach wie vielen Minuten ohne Aktivität soll ein Match als unvollständig markiert werden?') }}
                    </flux:description>
                    @error('matchTimeoutMinutes')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Scheduler-Intervall (Minuten)') }}</flux:label>
                    <flux:input wire:model="intervalMinutes" type="number" min="1" max="1440" required />
                    <flux:description>
                        {{ __('Wie oft soll der Scheduler ausgeführt werden?') }}
                    </flux:description>
                    @error('intervalMinutes')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Log-Aufbewahrung (Tage)') }}</flux:label>
                    <flux:input wire:model="logRetentionDays" type="number" min="1" max="365" required />
                    <flux:description>
                        {{ __('Wie lange sollen Scheduler-Logs aufbewahrt werden?') }}
                    </flux:description>
                    @error('logRetentionDays')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="outline" wire:click="runCommand" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="runCommand">{{ __('Command jetzt ausführen') }}</span>
                        <span wire:loading wire:target="runCommand">{{ __('Wird ausgeführt...') }}</span>
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('Speichern') }}</span>
                        <span wire:loading>{{ __('Wird gespeichert...') }}</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </x-page-settings.layout>

    <!-- Logs-Tabelle -->
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Scheduler Logs') }}</flux:heading>
            <flux:subheading>{{ __('Übersicht über die letzten Scheduler-Ausführungen') }}</flux:subheading>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Scheduler') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Status') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Nachricht') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Betroffene Datensätze') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Ausgeführt am') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($logs as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $log->scheduler_name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                <flux:badge
                                    size="sm"
                                    :variant="$log->status === 'success' ? 'success' : 'danger'"
                                >
                                    {{ $log->status === 'success' ? __('Erfolg') : __('Fehler') }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $log->message ?? __('Keine Nachricht') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $log->affected_records }}
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $log->executed_at->format('d.m.Y H:i:s') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Keine Logs vorhanden.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="flex justify-center">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</section>
