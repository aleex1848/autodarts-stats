@php
    $season = $data['season'];
    $participant = $data['participant'];
@endphp

<div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        {{-- Statistik-Grid --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Gespielte Spiele') }}
                </flux:text>
                <flux:heading size="xl" class="mt-1">
                    {{ $participant->matches_played }}
                </flux:heading>
            </div>
            <div>
                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Verbleibende Spiele') }}
                </flux:text>
                <flux:heading size="xl" class="mt-1">
                    {{ $data['remainingFixtures'] }}
                </flux:heading>
            </div>
            <div>
                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Aktuelle Position') }}
                </flux:text>
                <flux:heading size="xl" class="mt-1">
                    @if($data['currentPosition'])
                        #{{ $data['currentPosition'] }}
                    @else
                        -
                    @endif
                </flux:heading>
            </div>
            <div>
                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Aktuelle Punkte') }}
                </flux:text>
                <flux:heading size="xl" class="mt-1">
                    {{ $participant->points }}
                </flux:heading>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Season Average') }}
                </flux:text>
                <flux:heading size="lg" class="mt-1">
                    @if($data['seasonAverage'])
                        {{ number_format($data['seasonAverage'], 2) }}
                    @else
                        -
                    @endif
                </flux:heading>
            </div>
            <div>
                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Win/Loss Ratio') }}
                </flux:text>
                <flux:heading size="lg" class="mt-1">
                    {{ number_format($data['winLossRatio'] * 100, 1) }}%
                </flux:heading>
            </div>
            <div>
                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Bestes Average') }}
                </flux:text>
                <flux:heading size="lg" class="mt-1">
                    @if($data['bestAverage'])
                        {{ number_format($data['bestAverage'], 2) }}
                    @else
                        -
                    @endif
                </flux:heading>
            </div>
            <div>
                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                    {{ __('180er') }}
                </flux:text>
                <flux:heading size="lg" class="mt-1">
                    {{ $data['total180s'] }}
                </flux:heading>
            </div>
        </div>

        {{-- Tabellenausschnitt --}}
        @if(!empty($data['tableSlice']))
            <div class="mt-6">
                <flux:heading size="md" class="mb-3">
                    {{ __('Tabellenausschnitt') }}
                </flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Platz') }}</flux:table.column>
                        <flux:table.column>{{ __('Spieler') }}</flux:table.column>
                        <flux:table.column>{{ __('Spiele') }}</flux:table.column>
                        <flux:table.column>{{ __('Legs') }}</flux:table.column>
                        <flux:table.column>{{ __('Punkte') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($data['tableSlice'] as $row)
                            <flux:table.row class="{{ $row['isCurrentUser'] ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                <flux:table.cell>
                                    {{ $row['position'] }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        {{ $row['participant']->player->name }}
                                        @if($row['isCurrentUser'])
                                            <flux:badge size="xs" variant="primary">{{ __('Du') }}</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $row['participant']->matches_played }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $row['participant']->legs_won }}:{{ $row['participant']->legs_lost }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $row['participant']->points }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        {{-- Verlaufsdiagramme --}}
        @if(!empty($data['progressData']))
            <div class="mt-6" x-data="{ activeTab: 'average' }">
                <flux:heading size="md" class="mb-3">
                    {{ __('Entwicklung') }}
                </flux:heading>
                
                <flux:tabs variant="segmented" x-model="activeTab">
                    <flux:tab name="average">{{ __('Season-Average') }}</flux:tab>
                    <flux:tab name="game-average">{{ __('Game-Average') }}</flux:tab>
                    <flux:tab name="position">{{ __('Position') }}</flux:tab>
                    <flux:tab name="ratio">{{ __('Win/Loss') }}</flux:tab>
                </flux:tabs>

                <div class="mt-4">
                    {{-- Average Chart --}}
                    <div x-show="activeTab === 'average'" x-transition class="h-64">
                        @include('livewire.season-standings.partials.chart', [
                            'data' => $data['progressData'] ?? [],
                            'key' => 'season_average',
                            'format' => 'decimal',
                            'inverted' => false,
                        ])
                    </div>

                    {{-- Game-Average Chart --}}
                    <div x-show="activeTab === 'game-average'" x-transition class="h-64">
                        @include('livewire.season-standings.partials.chart', [
                            'data' => $data['progressData'] ?? [],
                            'key' => 'game_average',
                            'format' => 'decimal',
                            'inverted' => false,
                        ])
                    </div>

                    {{-- Position Chart --}}
                    <div x-show="activeTab === 'position'" x-transition class="h-64">
                        @include('livewire.season-standings.partials.chart', [
                            'data' => $data['progressData'],
                            'key' => 'position',
                            'label' => __('Position'),
                            'format' => 'integer',
                            'inverted' => true,
                        ])
                    </div>

                    {{-- Win/Loss Ratio Chart --}}
                    <div x-show="activeTab === 'ratio'" x-transition class="h-64">
                        @include('livewire.season-standings.partials.chart', [
                            'data' => $data['progressData'],
                            'key' => 'win_loss_ratio',
                            'label' => __('Win/Loss Ratio'),
                            'format' => 'percentage',
                        ])
                    </div>
                </div>
            </div>
        @endif

    {{-- Nächstes Spiel --}}
    @if($data['nextFixture'])
        <div class="mt-6 rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-zinc-800">
            <flux:heading size="sm" class="mb-2">
                {{ __('Nächstes Spiel') }}
            </flux:heading>
            <flux:text size="sm">
                {{ __('Spieltag :number', ['number' => $data['nextFixture']->matchday_number]) }}
            </flux:text>
        </div>
    @endif
</div>
