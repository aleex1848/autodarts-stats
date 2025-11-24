@php
    use App\Models\Download;

    $latestDownloads = Download::query()
        ->where('is_active', true)
        ->with(['category', 'creator'])
        ->latest()
        ->limit(5)
        ->get();
@endphp

<div class="relative h-full overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex h-full flex-col">
        <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-700 dark:bg-zinc-800">
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Neueste Downloads') }}</h3>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            @forelse ($latestDownloads as $download)
                <a
                    href="{{ route('downloads.show', $download) }}"
                    wire:navigate
                    class="group mb-4 block rounded-lg border border-neutral-200 bg-neutral-50 p-4 transition-colors hover:border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-zinc-800 dark:hover:border-neutral-600 dark:hover:bg-zinc-700"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <h4 class="truncate text-sm font-medium text-neutral-900 group-hover:text-neutral-700 dark:text-neutral-100 dark:group-hover:text-neutral-300">
                                {{ $download->title }}
                            </h4>
                            @if ($download->category)
                                <div class="mt-1">
                                    <span class="inline-flex items-center rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200">
                                        {{ $download->category->name }}
                                    </span>
                                </div>
                            @endif
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $download->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="shrink-0">
                            <svg class="size-5 text-neutral-400 transition-colors group-hover:text-neutral-600 dark:text-neutral-500 dark:group-hover:text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </div>
                    </div>
                </a>
            @empty
                <div class="py-8 text-center">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Keine Downloads verfügbar.') }}</p>
                </div>
            @endforelse
        </div>

        @if ($latestDownloads->count() > 0)
            <div class="border-t border-neutral-200 bg-neutral-50 px-6 py-3 dark:border-neutral-700 dark:bg-zinc-800">
                <a
                    href="{{ route('admin.downloads.index') }}"
                    wire:navigate
                    class="text-sm font-medium text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
                >
                    {{ __('Alle Downloads anzeigen') }} →
                </a>
            </div>
        @endif
    </div>
</div>


