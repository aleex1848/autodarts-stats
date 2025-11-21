<?php

use App\Actions\SplitLeague;
use App\Enums\LeagueStatus;
use App\Enums\RegistrationStatus;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\LeagueRegistration;
use App\Models\Player;
use App\Services\LeagueScheduler;
use App\Services\LeagueStandingsCalculator;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public League $league;

    public string $activeTab = 'overview';
    public bool $showAddParticipantModal = false;
    public bool $showSplitModal = false;
    public ?int $selectedPlayerId = null;
    public string $playerSearch = '';
    public int $numberOfSplits = 2;

    public function mount(League $league): void
    {
        $this->league = $league->load([
            'registrations.player',
            'participants.player',
            'matchdays.fixtures.homePlayer',
            'matchdays.fixtures.awayPlayer',
        ]);
    }

    public function with(): array
    {
        return [
            'registrations' => $this->league->registrations,
            'participants' => $this->league->participants,
            'matchdays' => $this->league->matchdays()->orderBy('matchday_number')->get(),
            'standings' => app(LeagueStandingsCalculator::class)->calculateStandings($this->league),
            'players' => Player::query()
                ->when($this->playerSearch !== '', function ($query) {
                    $search = '%' . $this->playerSearch . '%';
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
                })
                ->whereNotIn('id', $this->league->participants->pluck('player_id'))
                ->orderBy('name')
                ->limit(20)
                ->get(),
        ];
    }

    public function confirmRegistration(int $registrationId): void
    {
        $registration = LeagueRegistration::findOrFail($registrationId);
        $registration->update(['status' => RegistrationStatus::Confirmed->value]);

        // Create participant
        LeagueParticipant::firstOrCreate([
            'league_id' => $this->league->id,
            'player_id' => $registration->player_id,
        ]);

        $this->dispatch('notify', title: __('Anmeldung bestätigt'));
    }

    public function rejectRegistration(int $registrationId): void
    {
        $registration = LeagueRegistration::findOrFail($registrationId);
        $registration->update(['status' => RegistrationStatus::Rejected->value]);

        $this->dispatch('notify', title: __('Anmeldung abgelehnt'));
    }

    public function removeParticipant(int $participantId): void
    {
        $participant = LeagueParticipant::findOrFail($participantId);
        $participant->delete();

        $this->dispatch('notify', title: __('Teilnehmer entfernt'));
    }

    public function openAddParticipantModal(): void
    {
        $this->showAddParticipantModal = true;
        $this->playerSearch = '';
        $this->selectedPlayerId = null;
    }

    public function addParticipant(): void
    {
        if (!$this->selectedPlayerId) {
            return;
        }

        LeagueParticipant::firstOrCreate([
            'league_id' => $this->league->id,
            'player_id' => $this->selectedPlayerId,
        ]);

        $this->showAddParticipantModal = false;
        $this->selectedPlayerId = null;
        $this->playerSearch = '';

        $this->dispatch('notify', title: __('Teilnehmer hinzugefügt'));
    }

    public function generateMatchdays(): void
    {
        if ($this->league->participants->count() < 2) {
            $this->dispatch('notify', title: __('Mindestens 2 Teilnehmer benötigt'), variant: 'error');
            
            return;
        }

        app(LeagueScheduler::class)->generateMatchdays($this->league, $this->league->participants);

        $this->league->update(['status' => LeagueStatus::Active->value]);

        $this->dispatch('notify', title: __('Spieltage generiert'));
        
        $this->activeTab = 'matchdays';
    }

    public function openSplitModal(): void
    {
        $this->showSplitModal = true;
    }

    public function splitLeague(): void
    {
        $participantCount = $this->league->participants->count();
        
        if ($participantCount < 2) {
            $this->dispatch('notify', title: __('Nicht genug Teilnehmer'), variant: 'error');
            
            return;
        }

        $playersPerSplit = (int) floor($participantCount / $this->numberOfSplits);
        $participants = $this->league->participants->shuffle();
        
        $splits = [];
        for ($i = 0; $i < $this->numberOfSplits; $i++) {
            $splits[] = [
                'player_ids' => $participants->skip($i * $playersPerSplit)
                    ->take($playersPerSplit)
                    ->pluck('player_id')
                    ->toArray(),
                'max_players' => $this->league->max_players,
            ];
        }

        app(SplitLeague::class)->handle($this->league, $splits);

        $this->showSplitModal = false;
        $this->dispatch('notify', title: __('Liga wurde aufgeteilt'));
        
        $this->redirect(route('admin.leagues.index'), navigate: true);
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

    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="$set('activeTab', 'overview')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'overview' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Übersicht') }}
        </button>

        <button
            wire:click="$set('activeTab', 'registrations')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'registrations' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Anmeldungen') }} ({{ $league->registrations->count() }})
        </button>

        <button
            wire:click="$set('activeTab', 'participants')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'participants' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Teilnehmer') }} ({{ $league->participants->count() }})
        </button>

        <button
            wire:click="$set('activeTab', 'matchdays')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'matchdays' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Spieltage') }} ({{ $league->matchdays->count() }})
        </button>

        <button
            wire:click="$set('activeTab', 'standings')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'standings' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Tabelle') }}
        </button>
    </div>

    @if ($activeTab === 'overview')
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-4">{{ __('Liga-Informationen') }}</flux:heading>

                <dl class="grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                        <dd class="mt-1">
                            <flux:badge :variant="match($league->status) {
                                'registration' => 'primary',
                                'active' => 'success',
                                'completed' => 'subtle',
                                'cancelled' => 'danger',
                                default => 'subtle'
                            }">
                                {{ __(ucfirst($league->status)) }}
                            </flux:badge>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Maximale Spielerzahl') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $league->max_players }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Modus') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ match($league->mode) {
                                'single_round' => __('Nur Hinrunde'),
                                'double_round' => __('Hin & Rückrunde'),
                                default => $league->mode
                            } }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Spielvariante') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ match($league->variant) {
                                '501_single_single' => '501 Single-In Single-Out',
                                '501_single_double' => '501 Single-In Double-Out',
                                default => $league->variant
                            } }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Spiellänge') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ match($league->match_format) {
                                'best_of_3' => 'Best of 3',
                                'best_of_5' => 'Best of 5',
                                default => $league->match_format
                            } }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Tage pro Spieltag') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $league->days_per_matchday }}</dd>
                    </div>

                    @if ($league->registration_deadline)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Anmeldeschluss') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $league->registration_deadline->format('d.m.Y H:i') }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($league->status === 'registration' && $league->participants->count() >= 2)
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="generateMatchdays" variant="primary" icon="calendar">
                        {{ __('Spieltage generieren') }}
                    </flux:button>

                    @if ($league->participants->count() > $league->max_players)
                        <flux:button wire:click="openSplitModal" variant="outline" icon="scissors">
                            {{ __('Liga aufteilen') }}
                        </flux:button>
                    @endif
                </div>
            @endif

            @if ($league->participants->count() > $league->max_players)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    {{ __('Die Liga hat mehr Teilnehmer (:count) als die maximale Spielerzahl (:max). Erwäge die Liga aufzuteilen.', [
                        'count' => $league->participants->count(),
                        'max' => $league->max_players
                    ]) }}
                </flux:callout>
            @endif
        </div>
    @endif

    @if ($activeTab === 'registrations')
        <div class="space-y-4">
            @if ($league->registrations->where('status', 'pending')->count() > 0)
                <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Spieler') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Anmeldedatum') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Status') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Aktionen') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($registrations as $registration)
                                <tr wire:key="registration-{{ $registration->id }}">
                                    <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $registration->player->name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $registration->registered_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <flux:badge
                                            size="sm"
                                            :variant="match($registration->status) {
                                                'pending' => 'primary',
                                                'confirmed' => 'success',
                                                'rejected' => 'danger',
                                                default => 'subtle'
                                            }"
                                        >
                                            {{ __(ucfirst($registration->status)) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        @if ($registration->status === 'pending')
                                            <div class="flex justify-end gap-2">
                                                <flux:button
                                                    size="xs"
                                                    variant="primary"
                                                    wire:click="confirmRegistration({{ $registration->id }})"
                                                >
                                                    {{ __('Bestätigen') }}
                                                </flux:button>

                                                <flux:button
                                                    size="xs"
                                                    variant="danger"
                                                    wire:click="rejectRegistration({{ $registration->id }})"
                                                >
                                                    {{ __('Ablehnen') }}
                                                </flux:button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Keine offenen Anmeldungen vorhanden.') }}
                </flux:callout>
            @endif
        </div>
    @endif

    @if ($activeTab === 'participants')
        <div class="space-y-4">
            <div class="flex justify-end">
                <flux:button wire:click="openAddParticipantModal" variant="primary" icon="plus" size="sm">
                    {{ __('Teilnehmer hinzufügen') }}
                </flux:button>
            </div>

            @if ($participants->count() > 0)
                <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Spieler') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Aktionen') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($participants as $participant)
                                <tr wire:key="participant-{{ $participant->id }}">
                                    <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $participant->player->name }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <flux:button
                                            size="xs"
                                            variant="danger"
                                            wire:click="removeParticipant({{ $participant->id }})"
                                        >
                                            {{ __('Entfernen') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Keine Teilnehmer vorhanden.') }}
                </flux:callout>
            @endif
        </div>
    @endif

    @if ($activeTab === 'matchdays')
        <div class="space-y-4">
            @if ($matchdays->count() > 0)
                @foreach ($matchdays as $matchday)
                    <div wire:key="matchday-{{ $matchday->id }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <flux:heading size="md">
                                    {{ __('Spieltag :number', ['number' => $matchday->matchday_number]) }}
                                    @if ($matchday->is_return_round)
                                        <span class="text-sm font-normal text-zinc-500">({{ __('Rückrunde') }})</span>
                                    @endif
                                    @if ($matchday->is_playoff)
                                        <span class="text-sm font-normal text-zinc-500">({{ __('Entscheidungsspiel') }})</span>
                                    @endif
                                </flux:heading>
                                @if ($matchday->deadline_at)
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Deadline: :date', ['date' => $matchday->deadline_at->format('d.m.Y')]) }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="space-y-2">
                            @forelse ($matchday->fixtures as $fixture)
                                <div wire:key="fixture-{{ $fixture->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div class="flex-1">
                                        <span class="font-medium">{{ $fixture->homePlayer->name }}</span>
                                        <span class="mx-2 text-zinc-500">vs</span>
                                        <span class="font-medium">{{ $fixture->awayPlayer->name }}</span>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        @if ($fixture->status === 'completed')
                                            <span class="text-sm">
                                                {{ $fixture->home_legs_won }} : {{ $fixture->away_legs_won }}
                                            </span>
                                            <flux:badge size="sm" variant="success">
                                                {{ __('Beendet') }}
                                            </flux:badge>
                                        @else
                                            <flux:badge size="sm" :variant="$fixture->status === 'overdue' ? 'danger' : 'subtle'">
                                                {{ __(ucfirst($fixture->status)) }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">{{ __('Keine Spiele geplant') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            @else
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Spieltage generiert.') }}
                </flux:callout>
            @endif
        </div>
    @endif

    @if ($activeTab === 'standings')
        <div class="space-y-4">
            @if ($standings->count() > 0)
                <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Pos.') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Spieler') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Pkt.') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Sp.') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('S') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('N') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Legs') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($standings as $standing)
                                <tr wire:key="standing-{{ $standing->id }}">
                                    <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $standing->final_position ?? $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $standing->player->name }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $standing->points }}
                                        @if ($standing->penalty_points > 0)
                                            <span class="text-xs text-red-500">(-{{ $standing->penalty_points }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->matches_played }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->matches_won }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->matches_lost }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->legs_won }}:{{ $standing->legs_lost }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Tabelle verfügbar.') }}
                </flux:callout>
            @endif
        </div>
    @endif

    <flux:modal wire:model="showAddParticipantModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Teilnehmer hinzufügen') }}</flux:heading>
            <flux:subheading>{{ __('Wähle einen Spieler aus') }}</flux:subheading>
        </div>

        <form wire:submit="addParticipant" class="space-y-4">
            <flux:input
                wire:model.live.debounce.300ms="playerSearch"
                type="search"
                icon="magnifying-glass"
                :placeholder="__('Spieler suchen...')"
            />

            <flux:select
                wire:model="selectedPlayerId"
                :label="__('Spieler')"
                required
            >
                <option value="">{{ __('Spieler auswählen') }}</option>
                @forelse ($players as $player)
                    <option value="{{ $player->id }}">
                        {{ $player->name }}
                        @if ($player->email)
                            ({{ $player->email }})
                        @endif
                    </option>
                @empty
                    <option value="" disabled>{{ __('Keine Spieler gefunden') }}</option>
                @endforelse
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showAddParticipantModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ __('Hinzufügen') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showSplitModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Liga aufteilen') }}</flux:heading>
            <flux:subheading>
                {{ __('Teile die :count Teilnehmer auf mehrere Ligen auf', ['count' => $league->participants->count()]) }}
            </flux:subheading>
        </div>

        <form wire:submit="splitLeague" class="space-y-4">
            <flux:input
                wire:model="numberOfSplits"
                :label="__('Anzahl der Ligen')"
                type="number"
                min="2"
                :max="$league->participants->count()"
                required
            />

            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Dies wird :count Ligen mit je ca. :players Spielern erstellen.', [
                    'count' => $numberOfSplits,
                    'players' => floor($league->participants->count() / max($numberOfSplits, 1))
                ]) }}
            </p>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showSplitModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ __('Liga aufteilen') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>

