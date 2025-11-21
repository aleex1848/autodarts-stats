<?php

use App\Models\DartMatch;
use App\Services\MatchReprocessingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

new class extends Component {
    use AuthorizesRequests;

    public DartMatch $match;
    public Collection $legs;

    public function mount(DartMatch $match): void
    {
        $this->authorize('view', $match);

        $this->match = $match->load([
            'players' => fn ($query) => $query->orderBy('match_player.player_index'),
            'winner',
        ]);

        $this->legs = $match->legs()
            ->with('winner')
            ->orderBy('set_number')
            ->orderBy('leg_number')
            ->get();
    }

    public function reprocessMatch(): void
    {
        $this->authorize('view', $this->match);

        try {
            $service = app(MatchReprocessingService::class);
            $service->reprocessMatch($this->match);

            // Reload match and legs after reprocessing
            $this->match->refresh();
            $this->match->load([
                'players' => fn ($query) => $query->orderBy('match_player.player_index'),
                'winner',
            ]);

            $this->legs = $this->match->legs()
                ->with('winner')
                ->orderBy('set_number')
                ->orderBy('leg_number')
                ->get();

            Session::flash('success', __('Match wurde erfolgreich erneut verarbeitet.'));
        } catch (\Exception $e) {
            Session::flash('error', __('Fehler beim erneuten Verarbeiten des Matches: :message', ['message' => $e->getMessage()]));
        }
    }

    public function with(): array
    {
        return [
            'match' => $this->match,
            'legs' => $this->legs,
        ];
    }
}; ?>

@include('livewire.matches.partials.details', [
    'title' => __('Match-Details (Admin)'),
    'subtitle' => __('Vollständige Analyse des ausgewählten Matches'),
    'backUrl' => route('admin.matches.index'),
    'backLabel' => __('Zurück zur Übersicht'),
])

@if (session('success'))
    <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/50 dark:text-green-200">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/50 dark:text-red-200">
        {{ session('error') }}
    </div>
@endif

<div class="flex justify-end gap-2">
    <flux:button
        variant="danger"
        icon="arrow-path"
        wire:click="reprocessMatch"
        wire:confirm="{{ __('Sind Sie sicher, dass Sie dieses Match erneut verarbeiten möchten? Alle verarbeiteten Daten werden gelöscht und die Rohdaten werden erneut verarbeitet. Diese Aktion kann nicht rückgängig gemacht werden.') }}"
    >
        {{ __('Match erneut verarbeiten') }}
    </flux:button>
</div>

