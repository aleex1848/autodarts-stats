<?php

use App\Models\DartMatch;
use App\Services\MatchImportService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    use WithFileUploads;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 15;
    public $importFile = null;
    public bool $importOverwrite = false;
    public bool $showImportModal = false;
    public bool $showDeleteModal = false;
    public ?int $matchIdBeingDeleted = null;
    public ?string $matchIdBeingDeletedString = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'matches' => $this->matchesQuery()->paginate($this->perPage),
        ];
    }

    protected function matchesQuery(): Builder
    {
        return DartMatch::query()
            ->with([
                'players' => fn ($query) => $query->orderBy('match_player.player_index'),
                'players.user',
                'winner',
                'fixture.matchday.season.league',
                'fixture',
            ])
            ->when($this->statusFilter === 'finished', fn ($query) => $query->whereNotNull('finished_at'))
            ->when($this->statusFilter === 'ongoing', fn ($query) => $query->whereNull('finished_at'))
            ->when($this->search !== '', function ($query) {
                $searchTerm = '%' . $this->search . '%';

                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('autodarts_match_id', 'like', $searchTerm)
                        ->orWhere('variant', 'like', $searchTerm)
                        ->orWhereHas('players', function ($playerQuery) use ($searchTerm) {
                            $playerQuery->where('name', 'like', $searchTerm);
                        });
                });
            })
            ->orderByDesc('started_at')
            ->orderByDesc('id');
    }

    public function openImportModal(): void
    {
        $this->showImportModal = true;
        $this->reset(['importFile', 'importOverwrite']);
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->reset(['importFile', 'importOverwrite']);
    }

    public function importMatch(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:json|max:10240',
        ]);

        try {
            $content = file_get_contents($this->importFile->getRealPath());
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Session::flash('error', __('Ungültige JSON-Datei.'));
                $this->closeImportModal();

                return;
            }

            $service = app(MatchImportService::class);
            $match = $service->importMatch($data, $this->importOverwrite);

            Session::flash('success', __('Match wurde erfolgreich importiert.'));
            $this->closeImportModal();

            $this->redirect(route('matches.show', $match));
        } catch (\Exception $e) {
            Session::flash('error', __('Fehler beim Importieren: :message', ['message' => $e->getMessage()]));
            $this->closeImportModal();
        }
    }

    public function confirmDelete(int $matchId): void
    {
        $match = DartMatch::findOrFail($matchId);
        $this->matchIdBeingDeleted = $match->id;
        $this->matchIdBeingDeletedString = $match->autodarts_match_id;
        $this->showDeleteModal = true;
    }

    public function deleteMatch(): void
    {
        if (! $this->matchIdBeingDeleted) {
            $this->showDeleteModal = false;
            return;
        }

        $match = DartMatch::with('fixture')->findOrFail($this->matchIdBeingDeleted);

        // Reset fixture if match was assigned to one
        if ($match->fixture) {
            $match->fixture->update([
                'dart_match_id' => null,
                'status' => 'scheduled',
                'played_at' => null,
            ]);
        }

        // Delete the match
        $match->delete();

        Session::flash('success', __('Match wurde erfolgreich gelöscht.'));
        $this->showDeleteModal = false;
        $this->matchIdBeingDeleted = null;
        $this->matchIdBeingDeletedString = null;
    }

    public function updatedShowDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('matchIdBeingDeleted', 'matchIdBeingDeletedString');
        }
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Alle Matches') }}</flux:heading>
            <flux:subheading>{{ __('Vollständige Liste aller aufgezeichneten Spiele') }}</flux:subheading>
        </div>
        <div>
            <flux:button
                variant="outline"
                icon="arrow-up-tray"
                wire:click="openImportModal"
            >
                {{ __('Match importieren') }}
            </flux:button>
        </div>
    </div>

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

    @if ($showImportModal)
        <flux:modal wire:model="showImportModal" name="import-match" :close-by-clicking-away="false">
            <form wire:submit="importMatch" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Match importieren') }}</flux:heading>
                    <flux:subheading>{{ __('Laden Sie eine exportierte Match-Datei hoch, um sie zu importieren.') }}</flux:subheading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Datei') }}</flux:label>
                    <flux:input
                        type="file"
                        wire:model="importFile"
                        accept=".json"
                    />
                    <flux:description>{{ __('Wählen Sie eine JSON-Datei aus, die zuvor exportiert wurde.') }}</flux:description>
                    @error('importFile')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="importOverwrite">
                        {{ __('Existierendes Match überschreiben') }}
                    </flux:checkbox>
                    <flux:description>{{ __('Wenn aktiviert, wird ein existierendes Match mit derselben autodarts_match_id überschrieben.') }}</flux:description>
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="closeImportModal"
                    >
                        {{ __('Abbrechen') }}
                    </flux:button>
                    <flux:button
                        type="submit"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove>{{ __('Importieren') }}</span>
                        <span wire:loading>{{ __('Importiere...') }}</span>
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            class="lg:col-span-2"
            :placeholder="__('Nach Match-ID oder Spieler suchen...')"
        />

        <flux:select wire:model.live="statusFilter" :label="__('Status')" placeholder=" " class="lg:col-span-1">
            <option value="all">{{ __('Alle') }}</option>
            <option value="ongoing">{{ __('Laufend') }}</option>
            <option value="finished">{{ __('Beendet') }}</option>
        </flux:select>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Match') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Spieler') }}
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
                @forelse ($matches as $match)
                    <tr wire:key="match-{{ $match->id }}">
                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                            <div class="flex flex-col">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold">{{ $match->variant }} · {{ $match->type }}</span>
                                    @if ($match->fixture?->matchday)
                                        <div class="flex items-center gap-1">
                                            <flux:badge size="xs" variant="subtle">
                                                {{ $match->fixture->matchday->season->league->slug }}
                                            </flux:badge>
                                            <flux:badge size="xs" variant="subtle">
                                                {{ __('Spieltag :number', ['number' => $match->fixture->matchday->matchday_number]) }}
                                            </flux:badge>
                                        </div>
                                    @endif
                                </div>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Match-ID') }}: {{ $match->autodarts_match_id }}
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $match->started_at?->format('d.m.Y H:i') ?? __('Unbekannter Start') }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                            <div class="flex flex-wrap gap-2">
                                @forelse ($match->players as $player)
                                    <flux:badge variant="subtle" size="sm">
                                        @if ($player->user)
                                            <a href="{{ route('users.show', $player->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                {{ $player->name ?? __('Player #:id', ['id' => $player->id]) }}
                                            </a>
                                        @else
                                            {{ $player->name ?? __('Player #:id', ['id' => $player->id]) }}
                                        @endif
                                    </flux:badge>
                                @empty
                                    <span class="text-xs text-zinc-500">{{ __('Keine Spieler erfasst') }}</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                            @if ($match->finished_at)
                                <flux:badge variant="success">
                                    {{ __('Beendet') }}
                                </flux:badge>
                            @else
                                <flux:badge variant="warning">
                                    {{ __('Laufend') }}
                                </flux:badge>
                            @endif
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                @if ($match->winner)
                                    {{ __('Sieger: :name', ['name' => $match->winner->name]) }}
                                @else
                                    {{ __('Kein Sieger erfasst') }}
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="outline"
                                    :href="route('matches.show', $match)"
                                    wire:navigate
                                    icon="arrow-top-right-on-square"
                                >
                                    {{ __('Details') }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="confirmDelete({{ $match->id }})"
                                    icon="trash"
                                >
                                    {{ __('Löschen') }}
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Keine Matches gefunden.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $matches->links(data: ['scrollTo' => false]) }}
    </div>

    <flux:modal wire:model="showDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Match löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll das Match ":id" wirklich gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden.', ['id' => $matchIdBeingDeletedString]) }}
            </flux:subheading>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Wenn dieses Match einem Spieltag zugeordnet war, wird die Zuordnung entfernt und das Spieltags-Spiel wieder auf "geplant" gesetzt.') }}
            </p>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button
                variant="ghost"
                wire:click="$set('showDeleteModal', false)"
            >
                {{ __('Abbrechen') }}
            </flux:button>
            <flux:button
                variant="danger"
                wire:click="deleteMatch"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>{{ __('Löschen') }}</span>
                <span wire:loading>{{ __('Lösche...') }}</span>
            </flux:button>
        </div>
    </flux:modal>
</section>


