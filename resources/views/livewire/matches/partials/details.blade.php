@php
    $statusBadge = $match->finished_at ? ['label' => __('Beendet'), 'variant' => 'success'] : ['label' => __('Laufend'), 'variant' => 'warning'];
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
            @if (! empty($backUrl))
                <flux:button icon="arrow-left" variant="ghost" :href="$backUrl" wire:navigate>
                    {{ $backLabel ?? __('Zurück') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
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

            <dl class="mt-4 space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
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
                <div class="flex justify-between">
                    <dt class="font-medium text-zinc-500">{{ __('Bull-Mode') }}</dt>
                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->bull_mode ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium text-zinc-500">{{ __('Max. Runden') }}</dt>
                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $match->max_rounds ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="md">{{ __('Spielverlauf') }}</flux:heading>

            @if ($legs->isNotEmpty())
                <div class="mt-4 space-y-4">
                    @foreach ($legs as $leg)
                        <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ __('Set :set · Leg :leg', ['set' => $leg->set_number, 'leg' => $leg->leg_number]) }}
                                </span>
                                <flux:badge variant="{{ $leg->winner ? 'success' : 'subtle' }}" size="sm">
                                    {{ $leg->winner?->name ?? __('Offen') }}
                                </flux:badge>
                            </div>

                            @if ($leg->started_at || $leg->finished_at)
                                <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                                    @if ($leg->started_at)
                                        {{ __('Start:') }} {{ $leg->started_at->format('d.m.Y H:i:s') }}
                                    @endif
                                    @if ($leg->finished_at)
                                        @if ($leg->started_at) · @endif
                                        {{ __('Ende:') }} {{ $leg->finished_at->format('d.m.Y H:i:s') }}
                                    @endif
                                </p>
                            @endif

                            @if ($leg->legPlayers->isNotEmpty())
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-zinc-200 text-xs dark:divide-zinc-700">
                                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                                            <tr>
                                                <th class="px-3 py-2 text-left">{{ __('Spieler') }}</th>
                                                <th class="px-3 py-2 text-right">{{ __('Average') }}</th>
                                                <th class="px-3 py-2 text-right">{{ __('Pfeile') }}</th>
                                                <th class="px-3 py-2 text-right">{{ __('Checkout %') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                                            @foreach ($leg->legPlayers as $player)
                                                <tr>
                                                    <td class="px-3 py-2 font-medium text-zinc-900 dark:text-zinc-100">
                                                        {{ $player->name }}
                                                    </td>
                                                    <td class="px-3 py-2 text-right text-zinc-600 dark:text-zinc-400">
                                                        @if (! is_null($player->pivot->average))
                                                            {{ number_format((float) $player->pivot->average, 2, ',', '.') }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-right text-zinc-600 dark:text-zinc-400">
                                                        @if (! is_null($player->pivot->darts_thrown))
                                                            {{ $player->pivot->darts_thrown }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-right text-zinc-600 dark:text-zinc-400">
                                                        @if (! is_null($player->pivot->checkout_rate))
                                                            {{ number_format((float) $player->pivot->checkout_rate * 100, 2, ',', '.') }}%
                                                            @if (! is_null($player->pivot->checkout_hits) && ! is_null($player->pivot->checkout_attempts))
                                                                ({{ $player->pivot->checkout_hits }}/{{ $player->pivot->checkout_attempts }})
                                                            @endif
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Für dieses Match wurden noch keine Legs aufgezeichnet.') }}
                </p>
            @endif
        </div>
    </div>

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
                        <th class="px-4 py-3 text-left">{{ __('Average') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Pfeile') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Checkout %') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('180er') }}</th>
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
                                            {{ $player->name ?? __('Player #:id', ['id' => $player->id]) }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $player->email ?? $player->country ?? __('Keine weiteren Daten') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $player->pivot->legs_won ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $player->pivot->sets_won ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if (! is_null($player->pivot->match_average))
                                    {{ number_format((float) $player->pivot->match_average, 2, ',', '.') }}
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
                            <td class="px-4 py-3">{{ $player->pivot->total_180s ?? 0 }}</td>
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
                            <td colspan="8" class="px-4 py-4 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Keine Spieler vorhanden.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>


