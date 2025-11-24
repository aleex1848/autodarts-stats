<?php

use App\Models\Download;
use Livewire\Volt\Component;

new class extends Component {
    public Download $download;

    public function mount(Download $download): void
    {
        $this->download = $download->load(['category', 'creator']);
    }

    public function with(): array
    {
        return [
            'media' => $this->download->getFirstMedia('files'),
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $download->title }}</flux:heading>
            @if ($download->category)
                <flux:subheading>
                    <flux:badge size="sm" variant="subtle">{{ $download->category->name }}</flux:badge>
                </flux:subheading>
            @endif
        </div>

        @if ($media)
            <flux:button icon="arrow-down-tray" variant="primary" href="{{ route('downloads.file', $download) }}">
                {{ __('Download') }}
            </flux:button>
        @endif
    </div>

    @if ($download->description)
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Beschreibung') }}</flux:heading>
            <div class="prose prose-zinc dark:prose-invert max-w-none">
                <p class="text-zinc-600 dark:text-zinc-400">{{ $download->description }}</p>
            </div>
        </div>
    @endif

    @if ($media)
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Datei-Informationen') }}</flux:heading>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Dateiname') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $media->file_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Dateigröße') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                        {{ number_format($media->size / 1024, 2) }} KB
                        ({{ number_format($media->size / 1024 / 1024, 2) }} MB)
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('MIME-Type') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $media->mime_type }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Hochgeladen am') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                        {{ $media->created_at->format('d.m.Y H:i') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Erstellt von') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $download->creator->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Erstellt am') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                        {{ $download->created_at->format('d.m.Y H:i') }}
                    </dd>
                </div>
            </dl>
        </div>
    @else
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-6 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Keine Datei verfügbar.') }}</p>
        </div>
    @endif
</section>


