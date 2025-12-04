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
        <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
            <div>
                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Autodarts-Liga.de News') }}
                </h3>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('Neueste Ankündigungen und Updates') }}
                </p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            @forelse ($news as $item)
                <div wire:key="platform-news-{{ $item->id }}" class="border-b border-neutral-200 px-4 py-3 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-zinc-800/50">
                    <a href="{{ route('news.show', $item) }}" wire:navigate class="block">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    @if ($item->category)
                                        <flux:badge size="xs" :variant="'subtle'">
                                            {{ $item->category->name }}
                                        </flux:badge>
                                    @endif
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $item->published_at?->format('d.m.Y') ?? $item->created_at->format('d.m.Y') }}
                                    </span>
                                </div>
                                <h4 class="text-sm font-medium text-neutral-900 dark:text-neutral-100 line-clamp-2">
                                    {{ $item->title }}
                                </h4>
                                @if ($item->excerpt)
                                    <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400 line-clamp-2">
                                        {{ $item->excerpt }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Keine News verfügbar') }}
                    </p>
                </div>
            @endforelse
        </div>

        @if ($totalCount > $limit)
            <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:button
                    variant="ghost"
                    size="sm"
                    :href="route('news.platform')"
                    wire:navigate
                    class="w-full"
                >
                    {{ __('Alle News anzeigen') }} ({{ $totalCount }})
                </flux:button>
            </div>
        @endif
    </div>
</div>

