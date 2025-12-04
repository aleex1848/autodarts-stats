<?php

use App\Models\News;
use App\Services\SettingsService;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $limit = SettingsService::getPlatformNewsCount();
        
        $news = News::query()
            ->platform()
            ->published()
            ->with(['category', 'creator'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $totalCount = News::query()
            ->platform()
            ->published()
            ->count();

        return [
            'news' => $news,
            'totalCount' => $totalCount,
            'limit' => $limit,
        ];
    }
}; ?>

<div class="relative h-full overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex h-full flex-col">
        <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-700 dark:bg-zinc-800">
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Autodarts-Liga.de News') }}</h3>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            @forelse ($news as $item)
                <div
                    class="group mb-4 rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-zinc-800"
                    wire:key="platform-news-{{ $item->id }}"
                >
                    <a
                        href="{{ route('news.show', $item) }}"
                        wire:navigate
                        class="block p-4 transition-colors hover:border-neutral-300 hover:bg-neutral-100 dark:hover:border-neutral-600 dark:hover:bg-zinc-700"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-2">
                                    @if ($item->category)
                                        <flux:badge size="xs" variant="subtle">
                                            {{ $item->category->name }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <h4 class="truncate text-sm font-medium text-neutral-900 group-hover:text-neutral-700 dark:text-neutral-100 dark:group-hover:text-neutral-300">
                                    {{ $item->title }}
                                </h4>
                                <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $item->published_at?->diffForHumans() ?? $item->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="shrink-0 self-center">
                                <svg class="size-5 text-neutral-400 transition-colors group-hover:text-neutral-600 dark:text-neutral-500 dark:group-hover:text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="py-8 text-center">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Keine News verfügbar') }}</p>
                </div>
            @endforelse
        </div>

        @if ($totalCount > $limit)
            <div class="border-t border-neutral-200 bg-neutral-50 px-6 py-3 dark:border-neutral-700 dark:bg-zinc-800">
                <a
                    href="{{ route('news.platform') }}"
                    wire:navigate
                    class="text-sm font-medium text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
                >
                    {{ __('Alle News anzeigen') }} ({{ $totalCount }}) →
                </a>
            </div>
        @endif
    </div>
</div>

