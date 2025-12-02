<?php

use App\Models\League;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showDeleteModal = false;
    public ?int $leagueIdBeingDeleted = null;
    public ?string $leagueNameBeingDeleted = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'page' => ['except' => 1],
    ];

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
            'leagues' => $query->orderByDesc('created_at')->paginate(10),
        ];
    }

    public function confirmDelete(int $leagueId): void
    {
        $league = League::findOrFail($leagueId);

        $this->leagueIdBeingDeleted = $league->id;
        $this->leagueNameBeingDeleted = $league->name;
        $this->showDeleteModal = true;
    }

    public function deleteLeague(): void
    {
        if ($this->leagueIdBeingDeleted) {
            $league = League::findOrFail($this->leagueIdBeingDeleted);
            $league->delete();

            $this->dispatch('notify', title: __('Liga gelöscht'));
        }

        $this->showDeleteModal = false;
        $this->leagueIdBeingDeleted = null;
        $this->leagueNameBeingDeleted = null;
    }

    public function updatedShowDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('leagueIdBeingDeleted', 'leagueNameBeingDeleted');
        }
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Ligenverwaltung') }}</flux:heading>
            <flux:subheading>{{ __('Verwalte alle Ligen und deren Spieltage') }}</flux:subheading>
        </div>

        <flux:button icon="plus" variant="primary" :href="route('admin.leagues.create')" wire:navigate>
            {{ __('Liga anlegen') }}
        </flux:button>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Liga suchen...')"
    />

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Name') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Saisons') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Erstellt von') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Aktionen') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($leagues as $league)
                    <tr wire:key="league-{{ $league->id }}">
                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                            <div class="flex flex-col">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold">{{ $league->name }}</span>
                                    @if ($league->slug)
                                        <flux:badge size="xs" variant="subtle">
                                            {{ $league->slug }}
                                        </flux:badge>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $league->seasons_count }} {{ __('Saison(en)') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $league->creator->name }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="xs"
                                    variant="outline"
                                    :href="route('admin.leagues.show', $league)"
                                    wire:navigate
                                >
                                    {{ __('Details') }}
                                </flux:button>

                                <flux:button
                                    size="xs"
                                    variant="outline"
                                    :href="route('admin.leagues.edit', $league)"
                                    wire:navigate
                                >
                                    {{ __('Bearbeiten') }}
                                </flux:button>

                                <flux:button
                                    size="xs"
                                    variant="danger"
                                    wire:click="confirmDelete({{ $league->id }})"
                                >
                                    {{ __('Löschen') }}
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Keine Ligen vorhanden.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $leagues->links(data: ['scrollTo' => false]) }}
    </div>

    <flux:modal wire:model="showDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Liga löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll die Liga ":name" wirklich gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden.', ['name' => $leagueNameBeingDeleted]) }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button variant="danger" wire:click="deleteLeague">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>
</section>


