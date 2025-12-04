<?php

use App\Models\League;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Season;
use App\Services\OpenAIService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Locked]
    public League $league;

    #[Url]
    public ?int $createNews = null;

    #[Url]
    public ?int $urlFixtureId = null;

    public string $search = '';

    public ?int $editingNewsId = null;
    public string $title = '';
    public string $slug = '';
    public string $content = '';
    public string $excerpt = '';
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
    public bool $isGeneratingAI = false;
    public ?string $aiGenerationError = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'page' => ['except' => 1],
        'createNews' => ['except' => null],
        'urlFixtureId' => ['except' => null],
    ];

    public function mount(League $league): void
    {
        $this->league = $league;
        if (! $league->isAdmin(auth()->user())) {
            abort(403);
        }

        // If createNews parameter is set, open the modal and pre-fill fields
        if ($this->createNews) {
            $this->openCreateModal();
            
            // Pre-fill fixture if provided
            if ($this->urlFixtureId) {
                $this->matchdayFixtureId = $this->urlFixtureId;
                // Set category to "Spielberichte"
                $spielberichteCategory = NewsCategory::where('slug', 'spielberichte')->first();
                if ($spielberichteCategory) {
                    $this->categoryId = $spielberichteCategory->id;
                }
                // Also set season and matchday from fixture
                $fixture = \App\Models\MatchdayFixture::find($this->urlFixtureId);
                if ($fixture) {
                    $this->matchdayId = $fixture->matchday_id;
                    $this->seasonId = $fixture->matchday->season_id;
                }
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->matchdayId = null;
        $this->matchdayFixtureId = null;
    }

    public function with(): array
    {
        $newsQuery = News::query()
            ->league()
            ->where('league_id', $this->league->id)
            ->with(['category', 'creator', 'season'])
            ->when($this->search !== '', function ($query) {
                $search = '%' . $this->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                        ->orWhere('excerpt', 'like', $search)
                        ->orWhere('content', 'like', $search);
                });
            })
            ->orderByDesc('created_at');

        return [
            'news' => $newsQuery->paginate(15),
            'seasons' => $this->league->seasons()->orderBy('name')->get(),
            'categories' => NewsCategory::query()->orderBy('name')->get(),
            'matchdays' => $this->getMatchdays(),
            'fixtures' => $this->getFixtures(),
        ];
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
            'seasonId' => ['nullable', 'exists:seasons,id'],
            'categoryId' => ['nullable', 'exists:news_categories,id'],
            'publishedAt' => ['nullable', 'date'],
            'isPublished' => ['boolean'],
        ];

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
        if (! $this->createNews) {
            $this->resetNewsForm();
        }
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

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $data = [
            'type' => 'league',
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'content' => $validated['content'],
            'excerpt' => $validated['excerpt'],
            'league_id' => $this->league->id,
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
            // Clear URL parameters
            $this->createNews = null;
            $this->urlFixtureId = null;
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
        // Don't reset if we're pre-filling from URL parameters
        if (! $this->createNews) {
            $this->reset('editingNewsId', 'title', 'slug', 'content', 'excerpt', 'seasonId', 'categoryId', 'matchdayId', 'matchdayFixtureId', 'publishedAt', 'isPublished');
        }
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

    public function generateAIMatchReport(int $fixtureId): void
    {
        $this->authorize('create', News::class, 'league');

        $fixture = \App\Models\MatchdayFixture::findOrFail($fixtureId);
        
        if (! $fixture->dartMatch) {
            $this->dispatch('notify', title: __('Fehler'), description: __('Das Spiel hat noch kein Match-Daten.'), variant: 'danger');
            return;
        }

        $this->isGeneratingAI = true;
        $this->aiGenerationError = null;

        try {
            $openAIService = app(OpenAIService::class);
            $content = $openAIService->generateMatchReport($fixture->dartMatch);

            // Validate content is not empty
            if (empty(trim($content))) {
                throw new \Exception('Die OpenAI API hat keinen Inhalt zurückgegeben. Bitte versuchen Sie es erneut oder wählen Sie ein anderes Modell.');
            }

            $title = $this->extractTitleFromContent($content);
            $excerpt = $this->extractExcerptFromContent($content);

            $spielberichteCategory = NewsCategory::where('slug', 'spielberichte')->first();

            $news = News::create([
                'type' => 'league',
                'title' => $title,
                'slug' => Str::slug($title),
                'content' => $content,
                'excerpt' => $excerpt,
                'league_id' => $this->league->id,
                'season_id' => $fixture->matchday->season_id,
                'category_id' => $spielberichteCategory?->id,
                'matchday_id' => $fixture->matchday_id,
                'matchday_fixture_id' => $fixtureId,
                'is_published' => true,
                'created_by_user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', title: __('KI-News erfolgreich erstellt'));
        } catch (\Exception $e) {
            $this->aiGenerationError = $e->getMessage();
            $this->dispatch('notify', title: __('Fehler bei der KI-Generierung'), description: $e->getMessage(), variant: 'danger');
        } finally {
            $this->isGeneratingAI = false;
        }
    }

    public function generateAIMatchdayReport(int $matchdayId): void
    {
        $this->authorize('create', News::class, 'league');

        $matchday = \App\Models\Matchday::findOrFail($matchdayId);

        $this->isGeneratingAI = true;
        $this->aiGenerationError = null;

        try {
            $openAIService = app(OpenAIService::class);
            $content = $openAIService->generateMatchdayReport($matchday);

            // Validate content is not empty
            if (empty(trim($content))) {
                throw new \Exception('Die OpenAI API hat keinen Inhalt zurückgegeben. Bitte versuchen Sie es erneut oder wählen Sie ein anderes Modell.');
            }

            $title = $this->extractTitleFromContent($content);
            $excerpt = $this->extractExcerptFromContent($content);

            $spieltagsberichtCategory = NewsCategory::where('slug', 'spieltagsbericht')->first();

            $news = News::create([
                'type' => 'league',
                'title' => $title,
                'slug' => Str::slug($title),
                'content' => $content,
                'excerpt' => $excerpt,
                'league_id' => $this->league->id,
                'season_id' => $matchday->season_id,
                'category_id' => $spieltagsberichtCategory?->id,
                'matchday_id' => $matchdayId,
                'is_published' => true,
                'created_by_user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', title: __('KI-News erfolgreich erstellt'));
        } catch (\Exception $e) {
            $this->aiGenerationError = $e->getMessage();
            $this->dispatch('notify', title: __('Fehler bei der KI-Generierung'), description: $e->getMessage(), variant: 'danger');
        } finally {
            $this->isGeneratingAI = false;
        }
    }

    protected function extractTitleFromContent(string $content): string
    {
        $lines = explode("\n", trim($content));
        $firstLine = trim($lines[0] ?? '');

        $firstLine = preg_replace('/^#+\s*/', '', $firstLine);

        if (strlen($firstLine) > 100) {
            $sentences = preg_split('/([.!?]+)/', $firstLine, 2, PREG_SPLIT_DELIM_CAPTURE);
            $firstLine = trim($sentences[0] . ($sentences[1] ?? ''));
        }

        if (empty($firstLine) || strlen($firstLine) < 10) {
            return __('Spielbericht');
        }

        return $firstLine;
    }

    protected function extractExcerptFromContent(string $content): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($content));
        $firstParagraph = trim($paragraphs[0] ?? '');

        if (strlen($firstParagraph) > 500) {
            $firstParagraph = substr($firstParagraph, 0, 497) . '...';
        }

        return $firstParagraph ?: substr(strip_tags($content), 0, 200);
    }
}; ?>

<div class="relative h-full overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex h-full flex-col">
        <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-700 dark:bg-zinc-800">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Liga News') }}</h3>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Verwalte News für diese Liga') }}</p>
                </div>

                <div class="flex gap-2">
                    <flux:button icon="plus" variant="primary" wire:click="openCreateModal" size="sm">
                        {{ __('News erstellen') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="mb-4">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('News durchsuchen...')"
                />
            </div>

            <div class="space-y-4">
                @forelse ($news as $item)
                    <div
                        class="group rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-zinc-800"
                        wire:key="news-{{ $item->id }}"
                    >
                        <div class="p-4 transition-colors hover:border-neutral-300 hover:bg-neutral-100 dark:hover:border-neutral-600 dark:hover:bg-zinc-700">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-2">
                                        <a href="{{ route('news.show', $item) }}" wire:navigate class="hover:underline">
                                            <h4 class="break-words text-sm font-medium text-neutral-900 group-hover:text-neutral-700 dark:text-neutral-100 dark:group-hover:text-neutral-300">
                                                {{ $item->title }}
                                            </h4>
                                        </a>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        @if ($item->season)
                                            <flux:badge size="xs" variant="subtle">{{ $item->season->name }}</flux:badge>
                                        @else
                                            <flux:badge size="xs" variant="subtle">{{ __('Allgemein') }}</flux:badge>
                                        @endif
                                        @if ($item->category)
                                            <flux:badge size="xs" variant="subtle">{{ $item->category->name }}</flux:badge>
                                        @endif
                                        @if ($item->is_published)
                                            <flux:badge size="xs" variant="success">{{ __('Veröffentlicht') }}</flux:badge>
                                        @else
                                            <flux:badge size="xs" variant="danger">{{ __('Entwurf') }}</flux:badge>
                                        @endif
                                    </div>

                                    @if ($item->excerpt)
                                        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400 line-clamp-2">
                                            {{ $item->excerpt }}
                                        </p>
                                    @endif

                                    <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($item->created_at)
                                            {{ $item->created_at->diffForHumans() }}
                                        @endif
                                    </p>
                                </div>
                                <div class="shrink-0 flex gap-2">
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
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Keine News vorhanden.') }}</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $news->links() }}
            </div>
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

            <flux:select wire:model.live="seasonId" :label="__('Season (optional)')" :placeholder="__('Allgemeine Liga News')">
                <flux:select.option value="">{{ __('Allgemeine Liga News') }}</flux:select.option>
                @foreach ($seasons as $season)
                    <flux:select.option value="{{ $season->id }}">{{ $season->name }}</flux:select.option>
                @endforeach
            </flux:select>

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
</div>

