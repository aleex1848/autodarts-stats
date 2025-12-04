<?php

use App\Models\News;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public News $news;

    public function mount(News $news): void
    {
        $this->authorize('view', $news);
        
        $this->news = $news->load([
            'category',
            'creator',
            'league',
            'season',
            'matchday.season.league',
            'fixture.homePlayer',
            'fixture.awayPlayer',
            'fixture.matchday.season.league',
            'fixture.dartMatch',
        ]);
    }
}; ?>

<section class="w-full space-y-6">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
            {{ __('Dashboard') }}
        </a>
        <span>/</span>
        @if ($news->isPlatformNews())
            <a href="{{ route('news.platform') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Platform News') }}
            </a>
        @else
            <a href="{{ route('news.leagues') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Liga News') }}
            </a>
        @endif
        <span>/</span>
        <span class="text-neutral-900 dark:text-neutral-100">{{ $news->title }}</span>
    </nav>

    {{-- Header --}}
    <div class="space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                    @if ($news->category)
                        <flux:badge :variant="'subtle'">
                            {{ $news->category->name }}
                        </flux:badge>
                    @endif
                    @if ($news->league)
                        <flux:badge :variant="'subtle'">
                            {{ $news->league->name }}
                        </flux:badge>
                    @endif
                    @if ($news->season)
                        <flux:badge :variant="'subtle'">
                            {{ $news->season->name }}
                        </flux:badge>
                    @endif
                </div>
                <flux:heading size="xl">{{ $news->title }}</flux:heading>
                <div class="mt-2 flex items-center gap-4 text-sm text-neutral-600 dark:text-neutral-400">
                    <span>
                        {{ __('Von') }} <strong>{{ $news->creator->name }}</strong>
                    </span>
                    <span>·</span>
                    <span>
                        {{ $news->published_at?->format('d.m.Y H:i') ?? $news->created_at->format('d.m.Y H:i') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Linked Matchday/Fixture Info --}}
        @if ($news->matchday || $news->fixture)
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="sm" class="mb-2">{{ __('Verknüpfte Informationen') }}</flux:heading>
                @if ($news->matchday)
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Spieltag') }}:</span>
                        <a href="{{ route('seasons.show', $news->matchday->season) }}?activeTab=schedule" wire:navigate>
                            <flux:badge :variant="'subtle'">
                                {{ __('Spieltag :number', ['number' => $news->matchday->matchday_number]) }} - {{ $news->matchday->season->name }}
                            </flux:badge>
                        </a>
                    </div>
                @endif
                @if ($news->fixture)
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Spiel') }}:</span>
                        <a href="{{ route('seasons.show', $news->fixture->matchday->season) }}?activeTab=schedule" wire:navigate>
                            <flux:badge :variant="'subtle'">
                                {{ $news->fixture->homePlayer->name ?? __('Player #:id', ['id' => $news->fixture->homePlayer->id]) }} vs {{ $news->fixture->awayPlayer->name ?? __('Player #:id', ['id' => $news->fixture->awayPlayer->id]) }}
                            </flux:badge>
                        </a>
                        @if ($news->fixture->dartMatch)
                            <span class="text-sm text-neutral-600 dark:text-neutral-400">·</span>
                            <a href="{{ route('matches.show', $news->fixture->dartMatch) }}" wire:navigate>
                                <flux:badge :variant="'subtle'">
                                    {{ __('Match anzeigen') }}
                                </flux:badge>
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Content --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <article class="prose prose-zinc dark:prose-invert max-w-none 
            prose-headings:font-bold 
            prose-headings:text-neutral-900 
            dark:prose-headings:text-neutral-100 
            prose-h1:text-4xl 
            prose-h1:mt-10 
            prose-h1:mb-6 
            prose-h1:leading-tight
            prose-h2:text-3xl 
            prose-h2:mt-10 
            prose-h2:mb-5 
            prose-h2:leading-tight
            prose-h3:text-2xl 
            prose-h3:mt-8 
            prose-h3:mb-4
            prose-h4:text-xl 
            prose-h4:mt-8 
            prose-h4:mb-4
            prose-p:text-neutral-700 
            dark:prose-p:text-neutral-300 
            prose-p:leading-relaxed 
            prose-p:text-base 
            prose-p:mb-6
            prose-a:text-blue-600 
            dark:prose-a:text-blue-400 
            prose-strong:text-neutral-900 
            dark:prose-strong:text-neutral-100 
            prose-ul:my-6 
            prose-ul:ml-6 
            prose-li:leading-relaxed">
            {!! $news->rendered_content !!}
        </article>
    </div>
</section>

