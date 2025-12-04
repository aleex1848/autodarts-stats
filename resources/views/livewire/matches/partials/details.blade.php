@php
    if ($match->finished_at) {
        $statusBadge = ['label' => __('Beendet'), 'variant' => 'success'];
    } elseif ($match->is_incomplete) {
        $statusBadge = ['label' => __('Unvollständig'), 'variant' => 'danger'];
    } else {
        $statusBadge = ['label' => __('Laufend'), 'variant' => 'warning'];
    }
    $duration = ($match->started_at && $match->finished_at)
        ? $match->started_at->diffForHumans($match->finished_at, [
            'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
            'parts' => 2,
        ])
        : null;
@endphp

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $title ?? __('Matchdetails') }}</flux:heading>
            <flux:subheading>{{ $subtitle ?? '' }}</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button 
                icon="arrow-left" 
                variant="ghost" 
                onclick="window.history.back(); return false;"
            >
                {{ __('Zurück') }}
            </flux:button>
        </div>
    </div>

    <!-- Kompakte Info-Karten oben -->
    <div class="grid gap-4 {{ $match->fixture ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }}">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <flux:heading size="md">{{ __('Matchübersicht') }}</flux:heading>
                <flux:badge variant="{{ $statusBadge['variant'] }}">
                    {{ $statusBadge['label'] }}
                </flux:badge>
            </div>

            <dl class="mt-4 space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                <div class="flex justify-between">
                    <dt class="font-medium text-zinc-500">{{ __('Variante') }}</dt>
                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->variant }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium text-zinc-500">{{ __('Match-Typ') }}</dt>
                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->type }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium text-zinc-500">{{ __('Start') }}</dt>
                    <dd class="text-zinc-900 dark:text-zinc-100">
                        {{ $match->started_at?->format('d.m.Y H:i') ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium text-zinc-500">{{ __('Ende') }}</dt>
                    <dd class="text-zinc-900 dark:text-zinc-100">
                        {{ $match->finished_at?->format('d.m.Y H:i') ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium text-zinc-500">{{ __('Dauer') }}</dt>
                    <dd class="text-zinc-900 dark:text-zinc-100">
                        {{ $duration ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium text-zinc-500">{{ __('Sieger') }}</dt>
                    <dd class="text-zinc-900 dark:text-zinc-100">
                        {{ $match->winner?->name ?? __('Noch offen') }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium text-zinc-500">{{ __('Match-ID') }}</dt>
                    <dd class="text-zinc-900 dark:text-zinc-100 font-mono text-xs">
                        {{ $match->autodarts_match_id }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="md">{{ __('Einstellungen') }}</flux:heading>

            @php
                $season = $match->fixture?->matchday?->season ?? null;
                $isLeagueMatch = $season !== null;
            @endphp

            @if ($isLeagueMatch && $match->variant === 'X01')
                {{-- Vergleichstabelle für Ligaspiele --}}
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="px-4 py-2 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Einstellung') }}</th>
                                <th class="px-4 py-2 text-center font-medium text-zinc-500 dark:text-zinc-400">{{ __('Match') }}</th>
                                <th class="px-4 py-2 text-center font-medium text-zinc-500 dark:text-zinc-400">{{ __('Season') }}</th>
                                <th class="px-4 py-2 text-center font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @php
                                $settings = [
                                    'base_score' => ['label' => __('Base Score'), 'match' => $match->base_score, 'season' => $season->base_score],
                                    'in_mode' => ['label' => __('In Mode'), 'match' => $match->in_mode, 'season' => $season->in_mode],
                                    'out_mode' => ['label' => __('Out Mode'), 'match' => $match->out_mode, 'season' => $season->out_mode],
                                    'bull_mode' => ['label' => __('Bull Mode'), 'match' => $match->bull_mode, 'season' => $season->bull_mode],
                                    'max_rounds' => ['label' => __('Max Rounds'), 'match' => $match->max_rounds, 'season' => $season->max_rounds],
                                    'bull_off' => ['label' => __('Bull-Off'), 'match' => $match->bull_off, 'season' => $season->bull_off],
                                ];
                                
                                // Match Mode Vergleich
                                $matchModeMatch = null;
                                $matchModeSeason = null;
                                if ($match->match_mode_type === 'Legs' && $match->match_mode_legs_count) {
                                    $matchModeMatch = __('Legs - First to :count leg', ['count' => $match->match_mode_legs_count]);
                                } elseif ($match->match_mode_type === 'Sets' && $match->match_mode_sets_count) {
                                    $matchModeMatch = __('Sets - First to :count sets', ['count' => $match->match_mode_sets_count]);
                                    if ($match->match_mode_legs_count) {
                                        $matchModeMatch .= ' · ' . __('First to :count leg', ['count' => $match->match_mode_legs_count]);
                                    }
                                } elseif ($match->match_mode_type) {
                                    $matchModeMatch = $match->match_mode_type;
                                }
                                
                                if ($season->match_mode_type === 'Legs' && $season->match_mode_legs_count) {
                                    $matchModeSeason = __('Legs - First to :count leg', ['count' => $season->match_mode_legs_count]);
                                } elseif ($season->match_mode_type === 'Sets' && $season->match_mode_sets_count) {
                                    $matchModeSeason = __('Sets - First to :count sets', ['count' => $season->match_mode_sets_count]);
                                    if ($season->match_mode_legs_count) {
                                        $matchModeSeason .= ' · ' . __('First to :count leg', ['count' => $season->match_mode_legs_count]);
                                    }
                                } elseif ($season->match_mode_type) {
                                    $matchModeSeason = $season->match_mode_type;
                                }
                                
                                $settings['match_mode'] = ['label' => __('Match Mode'), 'match' => $matchModeMatch, 'season' => $matchModeSeason];
                            @endphp
                            
                            @foreach ($settings as $key => $setting)
                                @php
                                    $matchValue = $setting['match'] ?? '—';
                                    $seasonValue = $setting['season'] ?? '—';
                                    $matches = $matchValue === $seasonValue && $matchValue !== '—' && $seasonValue !== '—';
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $setting['label'] }}</td>
                                    <td class="px-4 py-2 text-center text-zinc-600 dark:text-zinc-400">{{ $matchValue }}</td>
                                    <td class="px-4 py-2 text-center text-zinc-600 dark:text-zinc-400">{{ $seasonValue }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if ($matches)
                                            <flux:badge variant="success" size="sm">
                                                <flux:icon name="check" class="h-3 w-3" />
                                            </flux:badge>
                                        @elseif ($matchValue !== '—' && $seasonValue !== '—')
                                            <flux:badge variant="danger" size="sm">
                                                <flux:icon name="x-mark" class="h-3 w-3 text-red-600 dark:text-red-400" />
                                            </flux:badge>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                {{-- Einfache Anzeige für Nicht-Ligaspiele --}}
                <dl class="mt-4 space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                    <div class="flex justify-between">
                        <dt class="font-medium text-zinc-500">{{ __('Variante') }}</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->variant ?? '—' }}</dd>
                    </div>
                    @if ($match->variant === 'X01')
                        <div class="flex justify-between">
                            <dt class="font-medium text-zinc-500">{{ __('Basisscore') }}</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->base_score ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-zinc-500">{{ __('In-Mode') }}</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->in_mode ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-zinc-500">{{ __('Out-Mode') }}</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->out_mode ?? '—' }}</dd>
                        </div>
                        @if ($match->bullOffs->isNotEmpty())
                            <div class="flex justify-between">
                                <dt class="font-medium text-zinc-500">{{ __('Bull-Mode') }}</dt>
                                <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->bull_mode ?? '—' }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="font-medium text-zinc-500">{{ __('Max. Runden') }}</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->max_rounds ?? '—' }}</dd>
                        </div>
                        @if ($match->bull_off)
                            <div class="flex justify-between">
                                <dt class="font-medium text-zinc-500">{{ __('Bull-Off') }}</dt>
                                <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->bull_off }}</dd>
                            </div>
                        @endif
                        @if ($match->match_mode_type)
                            <div class="flex justify-between">
                                <dt class="font-medium text-zinc-500">{{ __('Match Mode') }}</dt>
                                <dd class="text-zinc-900 dark:text-zinc-100">
                                    @if ($match->match_mode_type === 'Legs' && $match->match_mode_legs_count)
                                        {{ __('Legs - First to :count leg', ['count' => $match->match_mode_legs_count]) }}
                                    @elseif ($match->match_mode_type === 'Sets' && $match->match_mode_sets_count)
                                        {{ __('Sets - First to :count sets', ['count' => $match->match_mode_sets_count]) }}
                                        @if ($match->match_mode_legs_count)
                                            · {{ __('First to :count leg', ['count' => $match->match_mode_legs_count]) }}
                                        @endif
                                    @else
                                        {{ $match->match_mode_type }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                    @endif
                </dl>
            @endif
        </div>

        @if ($match->fixture)
            @php
                $fixture = $match->fixture;
                $matchday = $fixture->matchday;
                $season = $matchday->season ?? null;
                $league = $season->league ?? null;
                
                $statusVariant = match($fixture->status) {
                    'completed' => 'success',
                    'overdue' => 'danger',
                    'walkover' => 'warning',
                    default => 'subtle',
                };
                
                $statusLabel = match($fixture->status) {
                    'completed' => __('Abgeschlossen'),
                    'overdue' => __('Überfällig'),
                    'walkover' => __('Walkover'),
                    default => __('Geplant'),
                };
            @endphp
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <flux:heading size="md">{{ __('Ligaspiel') }}</flux:heading>
                    <flux:badge variant="{{ $statusVariant }}">
                        {{ $statusLabel }}
                    </flux:badge>
                </div>

                <dl class="mt-4 space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                    @if ($season)
                        <div class="flex justify-between">
                            <dt class="font-medium text-zinc-500">{{ __('Saison') }}</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">
                                <a href="{{ route('seasons.show', ['season' => $season, 'activeTab' => 'standings']) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" wire:navigate>
                                    {{ $season->name }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    
                    @if ($matchday)
                        <div class="flex justify-between">
                            <dt class="font-medium text-zinc-500">{{ __('Spieltag') }}</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">
                                @if ($season)
                                    <a href="{{ route('seasons.show', ['season' => $season, 'activeTab' => 'schedule']) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" wire:navigate>
                                        {{ __('Spieltag :number', ['number' => $matchday->matchday_number]) }}
                                        @if ($matchday->is_return_round)
                                            ({{ __('Rückrunde') }})
                                        @endif
                                    </a>
                                @else
                                    {{ __('Spieltag :number', ['number' => $matchday->matchday_number]) }}
                                    @if ($matchday->is_return_round)
                                        ({{ __('Rückrunde') }})
                                    @endif
                                @endif
                            </dd>
                        </div>
                    @endif
                    
                    @if ($league)
                        <div class="flex justify-between">
                            <dt class="font-medium text-zinc-500">{{ __('Liga') }}</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">
                                <a href="{{ route('leagues.show', $league) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" wire:navigate>
                                    {{ $league->name }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    
                    <div class="flex justify-between">
                        <dt class="font-medium text-zinc-500">{{ __('Spieler') }}</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">
                            <div class="text-right">
                                @if ($fixture->homePlayer)
                                    <div>
                                        @if ($fixture->homePlayer->user)
                                            <a href="{{ route('users.show', $fixture->homePlayer->user) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" target="_blank">
                                                {{ $fixture->homePlayer->name }}
                                            </a>
                                        @else
                                            {{ $fixture->homePlayer->name }}
                                        @endif
                                    </div>
                                @endif
                                @if ($fixture->awayPlayer)
                                    <div class="mt-1">
                                        <span class="text-zinc-500 dark:text-zinc-400">vs</span>
                                        @if ($fixture->awayPlayer->user)
                                            <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" class="ml-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" target="_blank">
                                                {{ $fixture->awayPlayer->name }}
                                            </a>
                                        @else
                                            <span class="ml-1">{{ $fixture->awayPlayer->name }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </dd>
                    </div>
                    
                    @if ($fixture->status === 'completed')
                        <div class="flex justify-between">
                            <dt class="font-medium text-zinc-500">{{ __('Ergebnis') }}</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100 font-semibold">
                                {{ $fixture->home_legs_won ?? 0 }} : {{ $fixture->away_legs_won ?? 0 }}
                            </dd>
                        </div>
                        
                        @if ($fixture->points_awarded_home !== null || $fixture->points_awarded_away !== null)
                            <div class="flex justify-between">
                                <dt class="font-medium text-zinc-500">{{ __('Punkte') }}</dt>
                                <dd class="text-zinc-900 dark:text-zinc-100">
                                    {{ $fixture->points_awarded_home ?? 0 }} : {{ $fixture->points_awarded_away ?? 0 }}
                                </dd>
                            </div>
                        @endif
                        
                        @if ($fixture->winner)
                            <div class="flex justify-between">
                                <dt class="font-medium text-zinc-500">{{ __('Gewinner') }}</dt>
                                <dd class="text-zinc-900 dark:text-zinc-100">
                                    @if ($fixture->winner->user)
                                        <a href="{{ route('users.show', $fixture->winner->user) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" target="_blank">
                                            {{ $fixture->winner->name }}
                                        </a>
                                    @else
                                        {{ $fixture->winner->name }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                    @endif
                    
                    @if ($fixture->played_at)
                        <div class="flex justify-between">
                            <dt class="font-medium text-zinc-500">{{ __('Gespielt am') }}</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">
                                {{ $fixture->played_at->format('d.m.Y H:i') }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif
    </div>

    <!-- Spielerübersicht in voller Breite -->
    <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="md">{{ __('Spielerübersicht') }}</flux:heading>
                <flux:subheading>{{ __('Alle Teilnehmer inklusive Pivot-Statistiken') }}</flux:subheading>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('Spieler') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Legs') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Sets') }}</th>
                        @if ($match->variant === 'X01')
                            <th class="px-4 py-3 text-left">{{ __('Average') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Avg. bis 170') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('First 9 Avg.') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Best Checkout') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Pfeile') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Checkout %') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('BUST') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('180er') }}</th>
                        @elseif ($match->variant === 'Cricket')
                            <th class="px-4 py-3 text-left">{{ __('MPR') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('First 9 MPR') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Pfeile') }}</th>
                        @else
                            <th class="px-4 py-3 text-left">{{ __('Pfeile') }}</th>
                        @endif
                        <th class="px-4 py-3 text-left">{{ __('Platzierung') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($match->players as $player)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar :src="$player->avatar_url" :name="$player->name" size="sm" />
                                    <div>
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                            @if ($player->user)
                                                <a href="{{ route('users.show', $player->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                    {{ $player->name ?? __('Player #:id', ['id' => $player->id]) }}
                                                </a>
                                            @else
                                                {{ $player->name ?? __('Player #:id', ['id' => $player->id]) }}
                                            @endif
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $player->email ?? $player->country ?? __('Keine weiteren Daten') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $player->pivot->legs_won ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $player->pivot->sets_won ?? '—' }}</td>
                            @if ($match->variant === 'X01')
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->match_average))
                                        {{ number_format((float) $player->pivot->match_average, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->average_until_170))
                                        {{ number_format((float) $player->pivot->average_until_170, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->first_9_average))
                                        {{ number_format((float) $player->pivot->first_9_average, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->best_checkout_points))
                                        {{ $player->pivot->best_checkout_points }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->darts_thrown))
                                        {{ $player->pivot->darts_thrown }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->checkout_rate))
                                        {{ number_format((float) $player->pivot->checkout_rate * 100, 2, ',', '.') }}%
                                        @if (! is_null($player->pivot->checkout_hits) && ! is_null($player->pivot->checkout_attempts))
                                            ({{ $player->pivot->checkout_hits }}/{{ $player->pivot->checkout_attempts }})
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->busted_count) && $player->pivot->busted_count > 0)
                                        <span class="font-medium text-red-600 dark:text-red-400">{{ $player->pivot->busted_count }}</span>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $player->pivot->total_180s ?? 0 }}</td>
                            @elseif ($match->variant === 'Cricket')
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->mpr))
                                        {{ number_format((float) $player->pivot->mpr, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->first_9_mpr))
                                        {{ number_format((float) $player->pivot->first_9_mpr, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->darts_thrown))
                                        {{ $player->pivot->darts_thrown }}
                                    @else
                                        —
                                    @endif
                                </td>
                            @else
                                <td class="px-4 py-3">
                                    @if (! is_null($player->pivot->darts_thrown))
                                        {{ $player->pivot->darts_thrown }}
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                @if ($player->pivot->final_position)
                                    <flux:badge variant="{{ $player->pivot->final_position === 1 ? 'success' : 'subtle' }}">
                                        {{ __('Platz :pos', ['pos' => $player->pivot->final_position]) }}
                                    </flux:badge>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $match->variant === 'X01' ? 12 : ($match->variant === 'Cricket' ? 6 : 5) }}" class="px-4 py-4 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Keine Spieler vorhanden.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bull-Off Anzeige -->
    @if ($match->bullOffs->isNotEmpty())
        <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="md">{{ __('Bull-Off') }}</flux:heading>
            <flux:subheading>
                {{ __('Beide Spieler werfen einmal auf Bull, um zu bestimmen, wer das Spiel beginnt.') }}
            </flux:subheading>

            <div class="mt-4 grid grid-cols-2 gap-4">
                @foreach ($match->bullOffs->sortBy('thrown_at') as $bullOff)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            @if ($bullOff->player?->user)
                                <a href="{{ route('users.show', $bullOff->player->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                    {{ $bullOff->player->name }}
                                </a>
                            @else
                                {{ $bullOff->player->name }}
                            @endif
                        </div>
                        <div class="mt-2 text-2xl font-bold {{ $bullOff->score < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                            {{ abs($bullOff->score) }}
                        </div>
                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Abstand vom Bull') }}
                        </div>
                    </div>
                @endforeach
            </div>

            @php
                // Bestimme den Gewinner (niedrigster Score gewinnt)
                $winner = $match->bullOffs->sortBy('score')->first();
            @endphp
            @if ($winner)
                <div class="mt-4 rounded-lg bg-emerald-50 p-4 dark:bg-emerald-900/20">
                    <div class="text-sm font-medium text-emerald-900 dark:text-emerald-100">
                        {{ __('Gewinner:') }}
                        @if ($winner->player?->user)
                            <a href="{{ route('users.show', $winner->player->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                {{ $winner->player->name }}
                            </a>
                        @else
                            {{ $winner->player->name }}
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
                        {{ __('Beginnt das Spiel') }}
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Legs-Vergleichstabelle -->
    @if ($legs->isNotEmpty())
        <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <flux:heading size="md">{{ __('Legs-Vergleich') }}</flux:heading>
                <flux:subheading>{{ __('Übersichtliche Darstellung aller Legs zum direkten Vergleich') }}</flux:subheading>
            </div>

            @php
                // Berechne beste Werte für Highlighting
                $bestCheckouts = [];
                $bestAverages = [];
                $bestCheckoutRates = [];
                
                foreach ($legs as $leg) {
                    foreach ($leg->legPlayers as $player) {
                        if ($match->variant === 'X01') {
                            if (!is_null($player->pivot->best_checkout_points)) {
                                $bestCheckouts[] = $player->pivot->best_checkout_points;
                            }
                            if (!is_null($player->pivot->average)) {
                                $bestAverages[] = $player->pivot->average;
                            }
                            if (!is_null($player->pivot->checkout_rate)) {
                                $bestCheckoutRates[] = $player->pivot->checkout_rate * 100;
                            }
                        }
                    }
                }
                
                $maxBestCheckout = !empty($bestCheckouts) ? max($bestCheckouts) : null;
                $maxBestAverage = !empty($bestAverages) ? max($bestAverages) : null;
                $maxBestCheckoutRate = !empty($bestCheckoutRates) ? max($bestCheckoutRates) : null;
            @endphp

            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'set'" :direction="$sortDirection" wire:click="sort('set')">{{ __('Set') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'leg'" :direction="$sortDirection" wire:click="sort('leg')">{{ __('Leg') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'player'" :direction="$sortDirection" wire:click="sort('player')">{{ __('Spieler') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'winner'" :direction="$sortDirection" wire:click="sort('winner')">{{ __('Gewinner') }}</flux:table.column>
                    @if ($match->variant === 'X01')
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'average'" :direction="$sortDirection" wire:click="sort('average')">{{ __('Average') }}</flux:table.column>
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'average_until_170'" :direction="$sortDirection" wire:click="sort('average_until_170')">{{ __('Avg. bis 170') }}</flux:table.column>
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'first_9_average'" :direction="$sortDirection" wire:click="sort('first_9_average')">{{ __('First 9 Avg.') }}</flux:table.column>
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'best_checkout'" :direction="$sortDirection" wire:click="sort('best_checkout')">{{ __('Best Checkout') }}</flux:table.column>
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'darts_thrown'" :direction="$sortDirection" wire:click="sort('darts_thrown')">{{ __('Pfeile') }}</flux:table.column>
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'checkout_rate'" :direction="$sortDirection" wire:click="sort('checkout_rate')">{{ __('Checkout %') }}</flux:table.column>
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'busted'" :direction="$sortDirection" wire:click="sort('busted')">{{ __('BUST') }}</flux:table.column>
                    @elseif ($match->variant === 'Cricket')
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'mpr'" :direction="$sortDirection" wire:click="sort('mpr')">{{ __('MPR') }}</flux:table.column>
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'first_9_mpr'" :direction="$sortDirection" wire:click="sort('first_9_mpr')">{{ __('First 9 MPR') }}</flux:table.column>
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'darts_thrown'" :direction="$sortDirection" wire:click="sort('darts_thrown')">{{ __('Pfeile') }}</flux:table.column>
                    @else
                        <flux:table.column align="end" sortable :sorted="$sortBy === 'darts_thrown'" :direction="$sortDirection" wire:click="sort('darts_thrown')">{{ __('Pfeile') }}</flux:table.column>
                    @endif
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($sortedLegRows as $row)
                        <flux:table.row>
                            <flux:table.cell>{{ $row['set_number'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['leg_number'] }}</flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $row['player_name'] }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($row['winner'])
                                    <flux:badge variant="success" size="sm">{{ __('Gewonnen') }}</flux:badge>
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('—') }}</span>
                                @endif
                            </flux:table.cell>
                            @if ($match->variant === 'X01')
                                <flux:table.cell align="end" variant="{{ !is_null($row['average']) && $row['average'] == $maxBestAverage ? 'strong' : 'base' }}">
                                    @if (!is_null($row['average']))
                                        {{ number_format((float) $row['average'], 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if (!is_null($row['average_until_170']))
                                        {{ number_format((float) $row['average_until_170'], 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if (!is_null($row['first_9_average']))
                                        {{ number_format((float) $row['first_9_average'], 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" variant="{{ !is_null($row['best_checkout_points']) && $row['best_checkout_points'] == $maxBestCheckout ? 'strong' : 'base' }}">
                                    @if (!is_null($row['best_checkout_points']))
                                        <span class="{{ $row['best_checkout_points'] == $maxBestCheckout ? 'font-semibold text-emerald-600 dark:text-emerald-400' : '' }}">
                                            {{ $row['best_checkout_points'] }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if (!is_null($row['darts_thrown']))
                                        {{ $row['darts_thrown'] }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" variant="{{ !is_null($row['checkout_rate']) && $row['checkout_rate'] == $maxBestCheckoutRate ? 'strong' : 'base' }}">
                                    @if (!is_null($row['checkout_rate']))
                                        {{ number_format((float) $row['checkout_rate'], 2, ',', '.') }}%
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($row['busted_count'] > 0)
                                        <span class="font-medium text-red-600 dark:text-red-400">{{ $row['busted_count'] }}</span>
                                    @else
                                        0
                                    @endif
                                </flux:table.cell>
                            @elseif ($match->variant === 'Cricket')
                                <flux:table.cell align="end">
                                    @if (!is_null($row['mpr']))
                                        {{ number_format((float) $row['mpr'], 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if (!is_null($row['first_9_mpr']))
                                        {{ number_format((float) $row['first_9_mpr'], 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if (!is_null($row['darts_thrown']))
                                        {{ $row['darts_thrown'] }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            @else
                                <flux:table.cell align="end">
                                    @if (!is_null($row['darts_thrown']))
                                        {{ $row['darts_thrown'] }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            @endif
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    <!-- Spielverlauf in voller Breite -->
    <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="md">{{ __('Spielverlauf') }}</flux:heading>
        <flux:subheading>{{ __('Detaillierte Ansicht aller Legs mit vollständigen Statistiken') }}</flux:subheading>

        @if ($legs->isNotEmpty())
            <div class="mt-4">
                <flux:accordion transition>
                    @foreach ($legs as $leg)
                        <flux:accordion.item>
                            <flux:accordion.heading>
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center gap-3">
                                        <span class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ __('Set :set · Leg :leg', ['set' => $leg->set_number, 'leg' => $leg->leg_number]) }}
                                        </span>
                                        @if ($leg->started_at && $leg->finished_at)
                                            @php
                                                $duration = $leg->started_at->diffForHumans($leg->finished_at, [
                                                    'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
                                                    'parts' => 2,
                                                ]);
                                            @endphp
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                <span class="font-medium">{{ __('Dauer: :duration', ['duration' => $duration]) }}</span>
                                            </span>
                                        @endif
                                    </div>
                                    <flux:badge variant="{{ $leg->winner ? 'success' : 'subtle' }}" size="sm">
                                        {{ $leg->winner?->name ?? __('Offen') }}
                                    </flux:badge>
                                </div>
                            </flux:accordion.heading>
                            <flux:accordion.content>
                                <div class="pt-4 space-y-6">
                                    @if ($leg->legPlayers->isNotEmpty())
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                                                    <tr>
                                                        <th class="px-4 py-3 text-left">{{ __('Spieler') }}</th>
                                                        @if ($match->variant === 'X01')
                                                            <th class="px-4 py-3 text-right">{{ __('Average') }}</th>
                                                            <th class="px-4 py-3 text-right">{{ __('Avg. bis 170') }}</th>
                                                            <th class="px-4 py-3 text-right">{{ __('First 9 Avg.') }}</th>
                                                            <th class="px-4 py-3 text-right">{{ __('Best Checkout') }}</th>
                                                            <th class="px-4 py-3 text-right">{{ __('Pfeile') }}</th>
                                                            <th class="px-4 py-3 text-right">{{ __('Checkout %') }}</th>
                                                            <th class="px-4 py-3 text-right">{{ __('BUST') }}</th>
                                                        @elseif ($match->variant === 'Cricket')
                                                            <th class="px-4 py-3 text-right">{{ __('MPR') }}</th>
                                                            <th class="px-4 py-3 text-right">{{ __('First 9 MPR') }}</th>
                                                            <th class="px-4 py-3 text-right">{{ __('Pfeile') }}</th>
                                                        @else
                                                            <th class="px-4 py-3 text-right">{{ __('Pfeile') }}</th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                                                    @foreach ($leg->legPlayers as $player)
                                                        <tr>
                                                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                                                {{ $player->name }}
                                                            </td>
                                                            @if ($match->variant === 'X01')
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->average))
                                                                        {{ number_format((float) $player->pivot->average, 2, ',', '.') }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->average_until_170))
                                                                        {{ number_format((float) $player->pivot->average_until_170, 2, ',', '.') }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->first_9_average))
                                                                        {{ number_format((float) $player->pivot->first_9_average, 2, ',', '.') }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->best_checkout_points))
                                                                        {{ $player->pivot->best_checkout_points }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->darts_thrown))
                                                                        {{ $player->pivot->darts_thrown }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->checkout_rate))
                                                                        {{ number_format((float) $player->pivot->checkout_rate * 100, 2, ',', '.') }}%
                                                                        @if (! is_null($player->pivot->checkout_hits) && ! is_null($player->pivot->checkout_attempts))
                                                                            ({{ $player->pivot->checkout_hits }}/{{ $player->pivot->checkout_attempts }})
                                                                        @endif
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->busted_count) && $player->pivot->busted_count > 0)
                                                                        <span class="font-medium text-red-600 dark:text-red-400">{{ $player->pivot->busted_count }}</span>
                                                                    @else
                                                                        0
                                                                    @endif
                                                                </td>
                                                            @elseif ($match->variant === 'Cricket')
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->mpr))
                                                                        {{ number_format((float) $player->pivot->mpr, 2, ',', '.') }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->first_9_mpr))
                                                                        {{ number_format((float) $player->pivot->first_9_mpr, 2, ',', '.') }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->darts_thrown))
                                                                        {{ $player->pivot->darts_thrown }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                            @else
                                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                                    @if (! is_null($player->pivot->darts_thrown))
                                                                        {{ $player->pivot->darts_thrown }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    @include('livewire.matches.partials.chalkboard', ['leg' => $leg])
                                </div>
                            </flux:accordion.content>
                        </flux:accordion.item>
                    @endforeach
                </flux:accordion>
            </div>
        @else
            <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Für dieses Match wurden noch keine Legs aufgezeichnet.') }}
            </p>
        @endif
    </div>
</section>


