<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        @php
            $activeHeader = \App\Models\Header::getActive();
            $headerMedia = $activeHeader?->getFirstMedia('header');
        @endphp

        @if($headerMedia)
            <div class="relative w-full overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <img
                    src="{{ $headerMedia->getUrl() }}"
                    srcset="{{ $headerMedia->getSrcset() }}"
                    sizes="100vw"
                    alt="{{ $activeHeader->name }}"
                    class="h-auto w-full object-cover"
                />
            </div>
        @endif

        {{-- Obere 3er Grid: Latest, Running, Upcoming --}}
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <livewire:matches.latest />
            <livewire:matches.running />
            <livewire:matches.upcoming />
        </div>

        {{-- Untere 3er Grid: Downloads --}}
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <x-downloads.latest />
        </div>

        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
</x-layouts.app>
