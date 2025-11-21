<?php

use App\Models\Download;
use App\Models\DownloadCategory;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public ?int $categoryFilter = null;
    public string $statusFilter = 'all';
    public int $perPage = 15;

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => null],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
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
            'downloads' => $this->downloadsQuery()->paginate($this->perPage),
            'categories' => DownloadCategory::query()->orderBy('name')->get(),
        ];
    }

    protected function downloadsQuery()
    {
        return Download::query()
            ->with(['category', 'creator'])
            ->when($this->search !== '', function ($query) {
                $search = '%' . $this->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            })
            ->when($this->categoryFilter, fn ($query) => $query->where('category_id', $this->categoryFilter))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest();
    }

    public function deleteDownload(int $downloadId): void
    {
        $download = Download::findOrFail($downloadId);
        $download->delete();

        session()->flash('success', __('Download wurde erfolgreich gelöscht.'));
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Downloads') }}</flux:heading>
            <flux:subheading>{{ __('Verwalte alle Downloads') }}</flux:subheading>
        </div>

        <flux:button icon="plus" variant="primary" href="{{ route('admin.downloads.create') }}" wire:navigate>
            {{ __('Download anlegen') }}
        </flux:button>
    </div>

    <div class="space-y-4">
        <div class="flex flex-wrap gap-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Suche...')"
                class="flex-1 min-w-[200px]"
            />

            <flux:select wire:model.live="categoryFilter" :placeholder="__('Alle Kategorien')" class="w-[200px]">
                <option value="">{{ __('Alle Kategorien') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" class="w-[150px]">
                <option value="all">{{ __('Alle Status') }}</option>
                <option value="active">{{ __('Aktiv') }}</option>
                <option value="inactive">{{ __('Inaktiv') }}</option>
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Titel') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Kategorie') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Status') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Erstellt von') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Erstellt am') }}
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Aktionen') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($downloads as $download)
                        <tr wire:key="download-{{ $download->id }}">
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                <a href="{{ route('admin.downloads.show', $download) }}" wire:navigate class="hover:underline">
                                    {{ $download->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                @if ($download->category)
                                    <flux:badge size="sm" variant="subtle">{{ $download->category->name }}</flux:badge>
                                @else
                                    <span class="text-zinc-400">{{ __('Keine Kategorie') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($download->is_active)
                                    <flux:badge size="sm" variant="success">{{ __('Aktiv') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" variant="danger">{{ __('Inaktiv') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $download->creator->name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $download->created_at->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="xs" variant="outline" href="{{ route('admin.downloads.show', $download) }}" wire:navigate>
                                        {{ __('Bearbeiten') }}
                                    </flux:button>

                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        wire:click="deleteDownload({{ $download->id }})"
                                        wire:confirm="{{ __('Möchtest du diesen Download wirklich löschen?') }}"
                                    >
                                        {{ __('Löschen') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Keine Downloads vorhanden.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $downloads->links() }}
        </div>
    </div>
</section>

