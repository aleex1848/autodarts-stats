<?php

use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';
    public ?int $categoryFilter = null;
    public string $statusFilter = 'all';

    public ?int $editingNewsId = null;
    public string $title = '';
    public string $slug = '';
    public string $content = '';
    public string $excerpt = '';
    public ?int $categoryId = null;
    public ?string $publishedAt = null;
    public bool $isPublished = false;
    public bool $showNewsFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $newsIdBeingDeleted = null;
    public ?string $newsTitleBeingDeleted = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => null],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->authorize('create', News::class, 'platform');
    }

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
            'news' => $this->newsQuery()->paginate(15),
            'categories' => NewsCategory::query()->orderBy('name')->get(),
        ];
    }

    protected function newsQuery()
    {
        return News::query()
            ->platform()
            ->with(['category', 'creator'])
            ->when($this->search !== '', function ($query) {
                $search = '%' . $this->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                        ->orWhere('excerpt', 'like', $search)
                        ->orWhere('content', 'like', $search);
                });
            })
            ->when($this->categoryFilter, fn ($query) => $query->where('category_id', $this->categoryFilter))
            ->when($this->statusFilter === 'published', fn ($query) => $query->where('is_published', true))
            ->when($this->statusFilter === 'draft', fn ($query) => $query->where('is_published', false))
            ->orderByDesc('created_at');
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(News::class, 'slug')->ignore($this->editingNewsId),
            ],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'categoryId' => ['nullable', 'exists:news_categories,id'],
            'publishedAt' => ['nullable', 'date'],
            'isPublished' => ['boolean'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', News::class, 'platform');
        $this->resetNewsForm();
        $this->showNewsFormModal = true;
    }

    public function editNews(int $newsId): void
    {
        $news = News::findOrFail($newsId);
        $this->authorize('update', $news);

        $this->editingNewsId = $news->id;
        $this->title = $news->title;
        $this->slug = $news->slug;
        $this->content = $news->content;
        $this->excerpt = $news->excerpt ?? '';
        $this->categoryId = $news->category_id;
        $this->publishedAt = $news->published_at?->format('Y-m-d\TH:i');
        $this->isPublished = $news->is_published;
        $this->showNewsFormModal = true;
    }

    public function saveNews(): void
    {
        $validated = $this->validate();

        // Auto-generate slug if empty
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $data = [
            'type' => 'platform',
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'content' => $validated['content'],
            'excerpt' => $validated['excerpt'],
            'category_id' => $validated['categoryId'],
            'published_at' => $validated['publishedAt'] ? now()->parse($validated['publishedAt']) : null,
            'is_published' => $validated['isPublished'],
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->editingNewsId) {
            $news = News::findOrFail($this->editingNewsId);
            $this->authorize('update', $news);
            // Don't update created_by_user_id when editing
            unset($data['created_by_user_id']);
            $news->update($data);
        } else {
            $this->authorize('create', News::class, 'platform');
            News::create($data);
        }

        $this->showNewsFormModal = false;
        $this->resetNewsForm();

        $this->dispatch('notify', title: __('News gespeichert'));
    }

    public function confirmDelete(int $newsId): void
    {
        $news = News::findOrFail($newsId);
        $this->authorize('delete', $news);

        $this->newsIdBeingDeleted = $news->id;
        $this->newsTitleBeingDeleted = $news->title;
        $this->showDeleteModal = true;
    }

    public function deleteNews(): void
    {
        if ($this->newsIdBeingDeleted) {
            $news = News::findOrFail($this->newsIdBeingDeleted);
            $this->authorize('delete', $news);
            $news->delete();
        }

        $this->showDeleteModal = false;
        $this->reset('newsIdBeingDeleted', 'newsTitleBeingDeleted');

        $this->dispatch('notify', title: __('News gelöscht'));
    }

    public function updatedShowNewsFormModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->resetNewsForm();
        }
    }

    public function updatedShowDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('newsIdBeingDeleted', 'newsTitleBeingDeleted');
        }
    }

    public function updatedTitle(string $value): void
    {
        if (! $this->editingNewsId && empty($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    protected function resetNewsForm(): void
    {
        $this->reset('editingNewsId', 'title', 'slug', 'content', 'excerpt', 'categoryId', 'publishedAt', 'isPublished');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Platform News') }}</flux:heading>
            <flux:subheading>{{ __('Verwalte alle Platform News') }}</flux:subheading>
        </div>

        <flux:button icon="plus" variant="primary" wire:click="openCreateModal">
            {{ __('News erstellen') }}
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
                <flux:select.option value="">{{ __('Alle Kategorien') }}</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" class="w-[150px]">
                <flux:select.option value="all">{{ __('Alle Status') }}</flux:select.option>
                <flux:select.option value="published">{{ __('Veröffentlicht') }}</flux:select.option>
                <flux:select.option value="draft">{{ __('Entwurf') }}</flux:select.option>
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
                    @forelse ($news as $item)
                        <tr wire:key="news-{{ $item->id }}">
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                <a href="{{ route('news.show', $item) }}" wire:navigate class="hover:underline">
                                    {{ $item->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                @if ($item->category)
                                    <flux:badge size="sm" variant="subtle">{{ $item->category->name }}</flux:badge>
                                @else
                                    <span class="text-zinc-400">{{ __('Keine Kategorie') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($item->is_published)
                                    <flux:badge size="sm" variant="success">{{ __('Veröffentlicht') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" variant="danger">{{ __('Entwurf') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $item->creator->name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $item->created_at->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="xs" variant="outline" wire:click="editNews({{ $item->id }})">
                                        {{ __('Bearbeiten') }}
                                    </flux:button>

                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        wire:click="confirmDelete({{ $item->id }})"
                                    >
                                        {{ __('Löschen') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Keine News vorhanden.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $news->links() }}
        </div>
    </div>

    <flux:modal wire:model="showNewsFormModal" class="space-y-6" max-width="4xl">
        <div>
            <flux:heading size="lg">
                {{ $editingNewsId ? __('News bearbeiten') : __('News erstellen') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Erstelle oder bearbeite eine Platform News') }}
            </flux:subheading>
        </div>

        <form wire:submit="saveNews" class="space-y-4">
            <flux:input
                wire:model="title"
                :label="__('Titel')"
                type="text"
                required
            />

            <flux:input
                wire:model="slug"
                :label="__('Slug')"
                type="text"
            />
            <flux:description>{{ __('Wird automatisch generiert, wenn leer gelassen') }}</flux:description>

            <flux:select wire:model="categoryId" :label="__('Kategorie')" :placeholder="__('Keine Kategorie')">
                <flux:select.option value="">{{ __('Keine Kategorie') }}</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="excerpt"
                :label="__('Excerpt (optional)')"
                rows="3"
                :placeholder="__('Kurze Zusammenfassung der News...')"
            />

            <flux:editor
                wire:model="content"
                :label="__('Inhalt')"
                :placeholder="__('Schreibe deine News hier...')"
                required
            />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:input
                    wire:model="publishedAt"
                    :label="__('Veröffentlichungsdatum (optional)')"
                    type="datetime-local"
                />

                <div class="flex items-end">
                    <flux:switch
                        wire:model="isPublished"
                        :label="__('Veröffentlicht')"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showNewsFormModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ $editingNewsId ? __('Aktualisieren') : __('Erstellen') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('News löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll die News ":title" wirklich gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden.', ['title' => $newsTitleBeingDeleted]) }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button variant="danger" wire:click="deleteNews">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>
</section>

