<?php

use App\Models\League;
use App\Models\Season;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public League $league;

    public string $activeTab = 'overview';

    public function mount(League $league): void
    {
        $this->league = $league->load([
            'creator',
            'coAdmins',
            'seasons',
        ]);
    }

    public function with(): array
    {
        return [
            'seasons' => $this->league->seasons()->orderByDesc('created_at')->get(),
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $league->name }}</flux:heading>
            <flux:subheading>
                {{ $league->description ?? __('Liga-Details und Verwaltung') }}
            </flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:button
                variant="outline"
                :href="route('admin.leagues.edit', $league)"
                wire:navigate
                icon="pencil"
            >
                {{ __('Bearbeiten') }}
            </flux:button>

            <flux:button
                variant="ghost"
                :href="route('admin.leagues.index')"
                wire:navigate
            >
                {{ __('Zurück') }}
            </flux:button>
        </div>
    </div>

    @if ($league->banner_path)
        <div class="overflow-hidden rounded-xl">
            <img src="{{ Storage::url($league->banner_path) }}" alt="{{ $league->name }}" class="w-full h-auto max-h-64 object-cover" />
        </div>
    @endif

    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="$set('activeTab', 'overview')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'overview' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Übersicht') }}
        </button>

        <button
            wire:click="$set('activeTab', 'seasons')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'seasons' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Saisons') }} ({{ $seasons->count() }})
        </button>
    </div>

    @if ($activeTab === 'overview')
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-4">{{ __('Liga-Informationen') }}</flux:heading>

                <dl class="grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $league->name }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Slug') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $league->slug }}</dd>
                    </div>

                    @if ($league->description)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Beschreibung') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $league->description }}</dd>
                        </div>
                    @endif

                    @if ($league->discord_invite_link)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Discord') }}</dt>
                            <dd class="mt-1">
                                <a href="{{ $league->discord_invite_link }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                    {{ $league->discord_invite_link }}
                                </a>
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Erstellt von') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ $league->creator->name }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Erstellt am') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ $league->created_at->format('d.m.Y H:i') }}
                        </dd>
                    </div>
                </dl>
            </div>

            @if ($league->coAdmins->count() > 0)
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg" class="mb-4">{{ __('Co-Administratoren') }}</flux:heading>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($league->coAdmins as $coAdmin)
                            <flux:badge size="sm" variant="subtle">
                                {{ $coAdmin->name }}
                            </flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($activeTab === 'seasons')
        <div class="space-y-4">
            <div class="flex justify-end">
                <flux:button
                    variant="primary"
                    icon="plus"
                    :href="route('admin.seasons.create', ['league' => $league->id])"
                    wire:navigate
                >
                    {{ __('Saison erstellen') }}
                </flux:button>
            </div>

            @if ($seasons->count() > 0)
                <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Name') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Status') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Teilnehmer') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Spieltage') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Aktionen') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($seasons as $season)
                                <tr wire:key="season-{{ $season->id }}">
                                    <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                        <div class="flex flex-col">
                                            <span class="font-semibold">{{ $season->name }}</span>
                                            @if ($season->slug)
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $season->slug }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <flux:badge
                                            size="sm"
                                            :variant="match($season->status) {
                                                'registration' => 'primary',
                                                'active' => 'success',
                                                'completed' => 'subtle',
                                                'cancelled' => 'danger',
                                                default => 'subtle'
                                            }"
                                        >
                                            {{ __(ucfirst($season->status)) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $season->participants()->count() }} / {{ $season->max_players }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $season->matchdays()->count() }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <flux:button
                                            size="xs"
                                            variant="outline"
                                            :href="route('admin.seasons.show', $season)"
                                            wire:navigate
                                        >
                                            {{ __('Anzeigen') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Saisons vorhanden.') }}
                </flux:callout>
            @endif
        </div>
    @endif
</section>
