<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        @php
            $header = null;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('headers')) {
                    $header = \App\Models\Header::getActive();
                }
            } catch (\Exception $e) {
                $header = null;
            }
        @endphp

        @if ($header && $header->hasMedia('header'))
            <div class="relative h-12 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                {!! $header->getFirstMedia('header')->img()->attributes(['class' => 'h-full w-full object-cover']) !!}
            </div>
        @else
            <div class="relative h-12 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        @endif

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <x-downloads.latest />
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>

        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
</x-layouts.app>
