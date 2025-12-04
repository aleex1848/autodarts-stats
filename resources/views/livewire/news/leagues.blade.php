<?php

use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $categoryId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryId' => ['except' => null],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryId(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $user = Auth::user();
        
        if (! $user || ! $user->player) {
            return [
                'news' => News::query()->whereRaw('1 = 0')->paginate(15),
                'categories' => NewsCategory::query()->orderBy('name')->get(),
            ];
        }

        // Get all league IDs where user is participant
        $leagueIds = $user->leagues()->pluck('id');
        
        // Get all season IDs where user is participant
        $seasonIds = \App\Models\SeasonParticipant::where('player_id', $user->player->id)
            ->pluck('season_id');

        $newsQuery = News::query()
            ->league()
            ->published()
            ->with(['category', 'creator', 'league', 'season'])
            ->where(function ($query) use ($leagueIds, $seasonIds) {
                // General league news (no season) for leagues where user participates
                $query->whereIn('league_id', $leagueIds)
                    ->whereNull('season_id');
                
                // Season-specific news for seasons where user participates
                if ($seasonIds->isNotEmpty()) {
                    $query->orWhereIn('season_id', $seasonIds);
                }
            })
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        if ($this->search !== '') {
            $newsQuery->where(function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('excerpt', 'like', '%' . $this->search . '%')
                    ->orWhere('content', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryId) {
            $newsQuery->where('category_id', $this->categoryId);
        }

        return [
            'news' => $newsQuery->paginate(15),
            'categories' => NewsCategory::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Meine Liga News') }}</flux:heading>
        <flux:subheading>{{ __('News aus deinen Ligen und Saisons') }}</flux:subheading>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-1 gap-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('News durchsuchen...')"
                class="flex-1"
            />
            <flux:select wire:model.live="categoryId" :placeholder="__('Alle Kategorien')">
                <flux:select.option value="">{{ __('Alle Kategorien') }}</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- News List --}}
    <div class="space-y-4">
        @forelse ($news as $item)
            <div wire:key="league-news-{{ $item->id }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <a href="{{ route('news.show', $item) }}" wire:navigate class="block">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                @if ($item->category)
                                    <flux:badge :variant="'subtle'">
                                        {{ $item->category->name }}
                                    </flux:badge>
                                @endif
                                @if ($item->league)
                                    <flux:badge :variant="'subtle'">
                                        {{ $item->league->name }}
                                    </flux:badge>
                                @endif
                                @if ($item->season)
                                    <flux:badge :variant="'subtle'">
                                        {{ $item->season->name }}
                                    </flux:badge>
                                @endif
                                <span class="text-sm text-neutral-500 dark:text-neutral-400">
                                    {{ $item->published_at?->format('d.m.Y') ?? $item->created_at->format('d.m.Y') }}
                                </span>
                            </div>
                            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
                                {{ $item->title }}
                            </h3>
                            @if ($item->excerpt)
                                <p class="text-sm text-neutral-600 dark:text-neutral-400 line-clamp-3">
                                    {{ $item->excerpt }}
                                </p>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Keine News gefunden') }}
                </p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div>
        {{ $news->links() }}
    </div>
</section>

