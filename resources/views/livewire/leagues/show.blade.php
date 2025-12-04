<?php

use App\Models\League;
use App\Models\Season;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public League $league;

    #[Url(as: 'activeTab')]
    public string $activeTab = 'overview';

    public function mount(League $league): void
    {
        $this->league = $league->load([
            'seasons' => function ($query) {
                $query->orderByDesc('created_at');
            },
        ]);

        // Validate activeTab value
        $validTabs = ['overview', 'seasons', 'news'];
        if (!in_array($this->activeTab, $validTabs)) {
            $this->activeTab = 'overview';
        }
    }

    public function with(): array
    {
        return [
            'seasons' => $this->league->seasons,
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $league->name }}</flux:heading>
            <flux:subheading>
                {{ $league->description ?? __('Liga-Details') }}
            </flux:subheading>
        </div>

        <flux:button variant="ghost" :href="route('leagues.index')" wire:navigate>
            {{ __('Zurück') }}
        </flux:button>
    </div>

    @if ($league->banner_path)
        <div class="overflow-hidden rounded-xl">
            <img src="{{ Storage::url($league->banner_path) }}" alt="{{ $league->name }}" class="w-full h-auto max-h-64 object-cover" />
        </div>
    @endif

    @if ($league->discord_invite_link)
        <div class="flex justify-center">
            <flux:button
                variant="primary"
                icon="arrow-top-right-on-square"
                :href="$league->discord_invite_link"
                target="_blank"
                rel="noopener noreferrer"
            >
                {{ __('Join Discord') }}
            </flux:button>
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

        @if ($league->isAdmin(Auth::user()))
            <button
                wire:click="$set('activeTab', 'news')"
                class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'news' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
            >
                {{ __('News') }}
            </button>
        @endif
    </div>

    @if ($activeTab === 'overview')
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-4">{{ __('Liga-Informationen') }}</flux:heading>

                @if ($league->description)
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $league->description }}</p>
                @endif

                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Saisons') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $seasons->count() }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    @endif

    @if ($activeTab === 'seasons')
        <div class="space-y-4">
            @if ($seasons->count() > 0)
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($seasons as $season)
                        <div wire:key="season-{{ $season->id }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            @if ($season->getBannerPath())
                                <div class="mb-4 overflow-hidden rounded-lg">
                                    <img src="{{ Storage::url($season->getBannerPath()) }}" alt="{{ $season->name }}" class="h-32 w-full object-cover" />
                                </div>
                            @endif

                            <flux:heading size="md" class="mb-2">{{ $season->name }}</flux:heading>

                            @if ($season->description)
                                <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">{{ Str::limit($season->description, 100) }}</p>
                            @endif

                            <div class="mb-4 space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</span>
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
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Teilnehmer') }}</span>
                                    <span class="font-medium">{{ $season->participants()->count() }} / {{ $season->max_players }}</span>
                                </div>
                            </div>

                            <flux:button
                                variant="outline"
                                :href="route('seasons.show', $season)"
                                wire:navigate
                                class="w-full"
                            >
                                {{ __('Saison anzeigen') }}
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Saisons vorhanden.') }}
                </flux:callout>
            @endif
        </div>
    @endif

    @if ($activeTab === 'news' && $league->isAdmin(Auth::user()))
        <livewire:leagues.news :league="$league" />
    @endif
</section>
