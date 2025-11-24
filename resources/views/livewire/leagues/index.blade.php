<?php

use App\Enums\LeagueStatus;
use App\Enums\RegistrationStatus;
use App\Models\League;
use App\Models\LeagueRegistration;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $tab = 'available';
    public ?int $playerId = null;

    public function mount(): void
    {
        $this->playerId = Auth::user()?->player?->id;
    }

    public function with(): array
    {
        return [
            'availableLeagues' => League::query()
                ->where('status', LeagueStatus::Registration->value)
                ->whereDoesntHave('registrations', function ($query) {
                    $query->where('player_id', $this->playerId);
                })
                ->withCount(['participants', 'registrations'])
                ->orderByDesc('created_at')
                ->paginate(10, pageName: 'available'),

            'myLeagues' => $this->playerId
                ? League::query()
                    ->whereHas('participants', function ($query) {
                        $query->where('player_id', $this->playerId);
                    })
                    ->whereIn('status', [LeagueStatus::Registration->value, LeagueStatus::Active->value])
                    ->with(['participants.player'])
                    ->orderByDesc('created_at')
                    ->paginate(10, pageName: 'my')
                : collect(),

            'completedLeagues' => $this->playerId
                ? League::query()
                    ->whereHas('participants', function ($query) {
                        $query->where('player_id', $this->playerId);
                    })
                    ->where('status', LeagueStatus::Completed->value)
                    ->with(['participants.player'])
                    ->orderByDesc('created_at')
                    ->paginate(10, pageName: 'completed')
                : collect(),

            'player' => Auth::user()?->player,
        ];
    }

    public function register(int $leagueId): void
    {
        if (!$this->playerId) {
            $this->dispatch('notify', title: __('Kein Player verknüpft'), variant: 'error');
            
            return;
        }

        $league = League::findOrFail($leagueId);

        if ($league->status !== LeagueStatus::Registration->value) {
            $this->dispatch('notify', title: __('Anmeldung nicht möglich'), variant: 'error');
            
            return;
        }

        LeagueRegistration::firstOrCreate([
            'league_id' => $leagueId,
            'player_id' => $this->playerId,
            'user_id' => Auth::id(),
        ], [
            'status' => RegistrationStatus::Pending->value,
            'registered_at' => now(),
        ]);

        $this->dispatch('notify', title: __('Anmeldung eingereicht'));
    }

    public function unregister(int $leagueId): void
    {
        $registration = LeagueRegistration::where('league_id', $leagueId)
            ->where('player_id', $this->playerId)
            ->first();

        if ($registration) {
            $registration->delete();
            $this->dispatch('notify', title: __('Anmeldung zurückgezogen'));
        }
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Ligen') }}</flux:heading>
        <flux:subheading>
            {{ __('Melde dich für Ligen an oder sieh deine Teilnahmen an') }}
        </flux:subheading>
    </div>

    @if (!$player)
        <flux:callout variant="warning" icon="user">
            {{ __('Es ist kein Player mit deinem Benutzer verknüpft. Bitte wende dich an einen Admin, um an Ligen teilnehmen zu können.') }}
        </flux:callout>
    @endif

    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="$set('tab', 'available')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $tab === 'available' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Verfügbare Ligen') }}
        </button>

        <button
            wire:click="$set('tab', 'my')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $tab === 'my' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Meine Ligen') }}
        </button>

        <button
            wire:click="$set('tab', 'completed')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $tab === 'completed' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Abgeschlossene Ligen') }}
        </button>
    </div>

    @if ($tab === 'available')
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($availableLeagues as $league)
                <div wire:key="available-league-{{ $league->id }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $league->name }}
                        </h3>
                        @if ($league->description)
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $league->description }}
                            </p>
                        @endif
                    </div>

                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Modus') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ match($league->mode) {
                                    'single_round' => __('Hinrunde'),
                                    'double_round' => __('Hin & Rückrunde'),
                                    default => $league->mode
                                } }}
                            </dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Anmeldungen') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $league->registrations_count }} / {{ $league->max_players }}
                            </dd>
                        </div>

                        @if ($league->registration_deadline)
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Deadline') }}</dt>
                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $league->registration_deadline->format('d.m.Y') }}
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-4">
                        @if ($player)
                            <flux:button wire:click="register({{ $league->id }})" variant="primary" class="w-full">
                                {{ __('Anmelden') }}
                            </flux:button>
                        @else
                            <flux:button disabled class="w-full">
                                {{ __('Kein Player') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <flux:callout variant="info" icon="information-circle">
                        {{ __('Keine verfügbaren Ligen.') }}
                    </flux:callout>
                </div>
            @endforelse
        </div>

        <div>
            {{ $availableLeagues->links(data: ['scrollTo' => false]) }}
        </div>
    @endif

    @if ($tab === 'my')
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($myLeagues as $league)
                <div wire:key="my-league-{{ $league->id }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ $league->name }}
                            </h3>
                            <flux:badge
                                size="sm"
                                class="mt-2"
                                :variant="$league->status === 'active' ? 'success' : 'primary'"
                            >
                                {{ __(ucfirst($league->status)) }}
                            </flux:badge>
                        </div>
                    </div>

                    @if ($league->description)
                        <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ Str::limit($league->description, 100) }}
                        </p>
                    @endif

                    <flux:button
                        :href="route('leagues.show', $league)"
                        wire:navigate
                        variant="outline"
                        class="w-full"
                    >
                        {{ __('Details ansehen') }}
                    </flux:button>
                </div>
            @empty
                <div class="col-span-full">
                    <flux:callout variant="info" icon="information-circle">
                        {{ __('Du nimmst derzeit an keinen Ligen teil.') }}
                    </flux:callout>
                </div>
            @endforelse
        </div>

        @if ($myLeagues instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div>
                {{ $myLeagues->links(data: ['scrollTo' => false]) }}
            </div>
        @endif
    @endif

    @if ($tab === 'completed')
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($completedLeagues as $league)
                <div wire:key="completed-league-{{ $league->id }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $league->name }}
                        </h3>
                        <flux:badge size="sm" class="mt-2" variant="subtle">
                            {{ __('Abgeschlossen') }}
                        </flux:badge>
                    </div>

                    <flux:button
                        :href="route('leagues.show', $league)"
                        wire:navigate
                        variant="outline"
                        class="w-full"
                    >
                        {{ __('Ergebnisse ansehen') }}
                    </flux:button>
                </div>
            @empty
                <div class="col-span-full">
                    <flux:callout variant="info" icon="information-circle">
                        {{ __('Du hast noch an keinen abgeschlossenen Ligen teilgenommen.') }}
                    </flux:callout>
                </div>
            @endforelse
        </div>

        @if ($completedLeagues instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div>
                {{ $completedLeagues->links(data: ['scrollTo' => false]) }}
            </div>
        @endif
    @endif
</section>


