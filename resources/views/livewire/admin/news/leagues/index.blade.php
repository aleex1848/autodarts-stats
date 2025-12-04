<?php

use App\Models\League;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Season;
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
    public ?int $leagueFilter = null;
    public ?int $seasonFilter = null;
    public ?int $categoryFilter = null;
    public string $statusFilter = 'all';

    public ?int $editingNewsId = null;
    public string $title = '';
    public string $slug = '';
    public string $content = '';
    public string $excerpt = '';
    public ?int $leagueId = null;
    public ?int $seasonId = null;
    public ?int $categoryId = null;
    public ?int $matchdayId = null;
    public ?int $matchdayFixtureId = null;
    public ?string $publishedAt = null;
    public bool $isPublished = false;
    public bool $showNewsFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $newsIdBeingDeleted = null;
    public ?string $newsTitleBeingDeleted = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'leagueFilter' => ['except' => null],
        'seasonFilter' => ['except' => null],
        'categoryFilter' => ['except' => null],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->authorize('create', News::class, 'league');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLeagueFilter(): void
    {
        $this->resetPage();
        $this->seasonFilter = null;
    }

    public function updatingSeasonFilter(): void
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

    public function updatedCategoryId(): void
    {
        // Reset matchday and fixture when category changes
        $this->matchdayId = null;
        $this->matchdayFixtureId = null;
    }

    public function updatedLeagueId(): void
    {
        // Reset season when league changes
        $this->seasonId = null;
    }

    public function with(): array
    {
        $seasonsQuery = Season::query()->orderBy('name');
        if ($this->leagueId) {
            $seasonsQuery->where('league_id', $this->leagueId);
        }

        return [
            'news' => $this->newsQuery()->paginate(15),
            'leagues' => League::query()->orderBy('name')->get(),
            'seasons' => $seasonsQuery->get(),
            'categories' => NewsCategory::query()->orderBy('name')->get(),
            'matchdays' => $this->getMatchdays(),
            'fixtures' => $this->getFixtures(),
        ];
    }

    protected function newsQuery()
    {
        return News::query()
            ->league()
            ->with(['category', 'creator', 'league', 'season'])
            ->when($this->search !== '', function ($query) {
                $search = '%' . $this->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                        ->orWhere('excerpt', 'like', $search)
                        ->orWhere('content', 'like', $search);
                });
            })
            ->when($this->leagueFilter, fn ($query) => $query->where('league_id', $this->leagueFilter))
            ->when($this->seasonFilter, fn ($query) => $query->where('season_id', $this->seasonFilter))
            ->when($this->categoryFilter, fn ($query) => $query->where('category_id', $this->categoryFilter))
            ->when($this->statusFilter === 'published', fn ($query) => $query->where('is_published', true))
            ->when($this->statusFilter === 'draft', fn ($query) => $query->where('is_published', false))
            ->orderByDesc('created_at');
    }

    protected function getMatchdays()
    {
        if (! $this->seasonId) {
            return collect();
        }

        return \App\Models\Matchday::query()
            ->where('season_id', $this->seasonId)
            ->with('season')
            ->orderBy('matchday_number')
            ->get();
    }

    protected function getFixtures()
    {
        if (! $this->seasonId) {
            return collect();
        }

        return \App\Models\MatchdayFixture::query()
            ->whereHas('matchday', fn ($q) => $q->where('season_id', $this->seasonId))
            ->with(['homePlayer', 'awayPlayer', 'matchday.season'])
            ->get();
    }

    protected function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(News::class, 'slug')->ignore($this->editingNewsId),
            ],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'leagueId' => ['required', 'exists:leagues,id'],
            'seasonId' => ['nullable', 'exists:seasons,id'],
            'categoryId' => ['nullable', 'exists:news_categories,id'],
            'publishedAt' => ['nullable', 'date'],
            'isPublished' => ['boolean'],
        ];

        // Get category slug to check which fields are required
        if ($this->categoryId) {
            $category = NewsCategory::find($this->categoryId);
            if ($category) {
                if ($category->slug === 'spieltagsbericht') {
                    $rules['matchdayId'] = ['nullable', 'exists:matchdays,id'];
                } elseif ($category->slug === 'spielberichte') {
                    $rules['matchdayFixtureId'] = ['nullable', 'exists:matchday_fixtures,id'];
                }
            }
        } else {
            $rules['matchdayId'] = ['nullable', 'exists:matchdays,id'];
            $rules['matchdayFixtureId'] = ['nullable', 'exists:matchday_fixtures,id'];
        }

        return $rules;
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', News::class, 'league');
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
        $this->leagueId = $news->league_id;
        $this->seasonId = $news->season_id;
        $this->categoryId = $news->category_id;
        $this->matchdayId = $news->matchday_id;
        $this->matchdayFixtureId = $news->matchday_fixture_id;
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
            'type' => 'league',
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'content' => $validated['content'],
            'excerpt' => $validated['excerpt'],
            'league_id' => $validated['leagueId'],
            'season_id' => $validated['seasonId'],
            'category_id' => $validated['categoryId'],
            'matchday_id' => $validated['matchdayId'] ?? null,
            'matchday_fixture_id' => $validated['matchdayFixtureId'] ?? null,
            'published_at' => $validated['publishedAt'] ? now()->parse($validated['publishedAt']) : null,
            'is_published' => $validated['isPublished'],
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->editingNewsId) {
            $news = News::findOrFail($this->editingNewsId);
            $this->authorize('update', $news);
            unset($data['created_by_user_id']);
            $news->update($data);
        } else {
            $this->authorize('create', News::class, 'league');
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
        $this->reset('editingNewsId', 'title', 'slug', 'content', 'excerpt', 'leagueId', 'seasonId', 'categoryId', 'matchdayId', 'matchdayFixtureId', 'publishedAt', 'isPublished');
    }

    protected function shouldShowMatchdayField(): bool
    {
        if (! $this->categoryId) {
            return false;
        }

        $category = NewsCategory::find($this->categoryId);
        return $category && $category->slug === 'spieltagsbericht';
    }

    protected function shouldShowFixtureField(): bool
    {
        if (! $this->categoryId) {
            return false;
        }

        $category = NewsCategory::find($this->categoryId);
        return $category && $category->slug === 'spielberichte';
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Liga News') }}</flux:heading>
            <flux:subheading>{{ __('Verwalte alle Liga/Saison News') }}</flux:subheading>
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

            <flux:select wire:model.live="leagueFilter" :placeholder="__('Alle Ligen')" class="w-[200px]">
                <flux:select.option value="">{{ __('Alle Ligen') }}</flux:select.option>
                @foreach ($leagues as $league)
                    <flux:select.option value="{{ $league->id }}">{{ $league->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="seasonFilter" :placeholder="__('Alle Seasons')" class="w-[200px]" :disabled="!$leagueFilter">
                <flux:select.option value="">{{ __('Alle Seasons') }}</flux:select.option>
                @foreach ($seasons as $season)
                    <flux:select.option value="{{ $season->id }}">{{ $season->name }}</flux:select.option>
                @endforeach
            </flux:select>

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
                            {{ __('Liga/Season') }}
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
                                <div class="flex flex-col gap-1">
                                    @if ($item->league)
                                        <flux:badge size="sm" variant="subtle">{{ $item->league->name }}</flux:badge>
                                    @endif
                                    @if ($item->season)
                                        <flux:badge size="sm" variant="subtle">{{ $item->season->name }}</flux:badge>
                                    @endif
                                </div>
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
                {{ __('Erstelle oder bearbeite eine Liga News') }}
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

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:select wire:model.live="leagueId" :label="__('Liga')" required>
                    <flux:select.option value="">{{ __('Liga auswählen') }}</flux:select.option>
                    @foreach ($leagues as $league)
                        <flux:select.option value="{{ $league->id }}">{{ $league->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="seasonId" :label="__('Season (optional)')" :placeholder="__('Allgemeine Liga News')" :disabled="!$leagueId">
                    <flux:select.option value="">{{ __('Allgemeine Liga News') }}</flux:select.option>
                    @foreach ($seasons as $season)
                        <flux:select.option value="{{ $season->id }}">{{ $season->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:select wire:model.live="categoryId" :label="__('Kategorie')" :placeholder="__('Keine Kategorie')">
                <flux:select.option value="">{{ __('Keine Kategorie') }}</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->shouldShowMatchdayField())
                <flux:select wire:model="matchdayId" :label="__('Spieltag')" :placeholder="__('Spieltag auswählen')" :disabled="!$seasonId">
                    <flux:select.option value="">{{ __('Kein Spieltag') }}</flux:select.option>
                    @foreach ($matchdays as $matchday)
                        <flux:select.option value="{{ $matchday->id }}">
                            {{ __('Spieltag :number', ['number' => $matchday->matchday_number]) }} - {{ $matchday->season->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if ($this->shouldShowFixtureField())
                <flux:select wire:model="matchdayFixtureId" :label="__('Spiel')" :placeholder="__('Spiel auswählen')" :disabled="!$seasonId">
                    <flux:select.option value="">{{ __('Kein Spiel') }}</flux:select.option>
                    @foreach ($fixtures as $fixture)
                        <flux:select.option value="{{ $fixture->id }}">
                            {{ $fixture->homePlayer->name ?? __('Player #:id', ['id' => $fixture->homePlayer->id]) }} vs {{ $fixture->awayPlayer->name ?? __('Player #:id', ['id' => $fixture->awayPlayer->id]) }} - {{ __('Spieltag :number', ['number' => $fixture->matchday->matchday_number]) }} - {{ $fixture->matchday->season->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

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

