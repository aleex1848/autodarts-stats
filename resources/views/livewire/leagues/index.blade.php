<?php

use App\Models\League;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public ?int $playerId = null;

    public function mount(): void
    {
        $this->playerId = Auth::user()?->player?->id;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = League::query()
            ->with(['creator'])
            ->withCount('seasons');

        if ($this->search !== '') {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        return [
            'leagues' => $query->orderByDesc('created_at')->paginate(12),
            'player' => Auth::user()?->player,
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Ligen') }}</flux:heading>
        <flux:subheading>
            {{ __('Alle verfügbaren Ligen') }}
        </flux:subheading>
    </div>

    @if (!$player)
        <flux:callout variant="warning" icon="user">
            {{ __('Es ist kein Player mit deinem Benutzer verknüpft. Bitte wende dich an einen Admin, um an Ligen teilnehmen zu können.') }}
        </flux:callout>
    @endif

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Liga suchen...')"
    />

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($leagues as $league)
            <div wire:key="league-{{ $league->id }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                @if ($league->banner_path)
                    <div class="mb-4 overflow-hidden rounded-lg">
                        <img src="{{ Storage::url($league->banner_path) }}" alt="{{ $league->name }}" class="h-32 w-full object-cover" />
                    </div>
                @endif

                <flux:heading size="md" class="mb-2">{{ $league->name }}</flux:heading>

                @if ($league->description)
                    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">{{ Str::limit($league->description, 100) }}</p>
                @endif

                <div class="mb-4 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Saisons') }}</span>
                        <span class="font-medium">{{ $league->seasons_count }}</span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <flux:button
                        variant="outline"
                        :href="route('leagues.show', $league)"
                        wire:navigate
                        class="flex-1"
                    >
                        {{ __('Details') }}
                    </flux:button>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Keine Ligen vorhanden.') }}
                </flux:callout>
            </div>
        @endforelse
    </div>

    <div>
        {{ $leagues->links(data: ['scrollTo' => false]) }}
    </div>
</section>
