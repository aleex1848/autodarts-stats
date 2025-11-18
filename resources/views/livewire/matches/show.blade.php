<?php

use App\Models\DartMatch;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
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

    public function with(): array
    {
        return [
            'match' => $this->match,
            'legs' => $this->legs,
        ];
    }
}; ?>

@include('livewire.matches.partials.details', [
    'title' => __('Matchdetails'),
    'subtitle' => __('Alle verfügbaren Statistiken zu diesem Match'),
    'backUrl' => route('matches.index'),
    'backLabel' => __('Zurück zu meinen Matches'),
])


