<?php

use App\Models\Download;
use App\Models\DownloadCategory;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Download $download;
    public string $title = '';
    public ?int $categoryId = null;
    public string $description = '';
    public bool $isActive = true;
    public $file = null;

    public function mount(Download $download): void
    {
        $this->download = $download->load(['category', 'creator']);
        $this->title = $download->title;
        $this->categoryId = $download->category_id;
        $this->description = $download->description ?? '';
        $this->isActive = $download->is_active;
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'categoryId' => ['nullable', 'exists:download_categories,id'],
            'description' => ['nullable', 'string'],
            'isActive' => ['boolean'],
            'file' => ['nullable', 'file', 'max:' . (100 * 1024)], // 100MB in KB
        ];
    }

    public function updatedFile($file): void
    {
        // Validierung wird beim Submit durchgeführt
    }

    public function with(): array
    {
        return [
            'categories' => DownloadCategory::query()->orderBy('name')->get(),
            'media' => $this->download->getFirstMedia('files'),
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $this->download->update([
            'title' => $validated['title'],
            'category_id' => $validated['categoryId'],
            'description' => $validated['description'],
            'is_active' => $validated['isActive'],
        ]);

        if ($this->file) {
            $path = $this->file->store('temp', 'local');
            $fullPath = storage_path('app/' . $path);

            $this->download->clearMediaCollection('files');
            $this->download->addMedia($fullPath)
                ->usingName($this->file->getClientOriginalName())
                ->usingFileName($this->file->getClientOriginalName())
                ->toMediaCollection('files');

            // Cleanup temp file
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $this->download->refresh();
        session()->flash('success', __('Download wurde erfolgreich aktualisiert.'));
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Download bearbeiten') }}</flux:heading>
            <flux:subheading>{{ __('Bearbeite Download-Details') }}</flux:subheading>
        </div>

        <flux:button variant="ghost" href="{{ route('admin.downloads.index') }}" wire:navigate>
            {{ __('Zurück zur Übersicht') }}
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="space-y-4">
            <flux:input wire:model="title" :label="__('Titel')" type="text" required />

            <flux:select wire:model="categoryId" :label="__('Kategorie')" :placeholder="__('Keine Kategorie')">
                <option value="">{{ __('Keine Kategorie') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="description" :label="__('Beschreibung')" rows="4" />

            <flux:switch wire:model="isActive" :label="__('Aktiv')" />

            @if ($media)
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Aktuelle Datei:') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $media->file_name }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-500">
                        {{ __('Größe:') }} {{ number_format($media->size / 1024, 2) }} KB
                        | {{ __('Typ:') }} {{ $media->mime_type }}
                    </p>
                    <div class="mt-2">
                        <flux:button size="sm" variant="outline" href="{{ route('downloads.file', $download) }}" target="_blank">
                            {{ __('Datei anzeigen') }}
                        </flux:button>
                    </div>
                </div>
            @endif

            <flux:field>
                <flux:label>{{ __('Neue Datei (optional)') }}</flux:label>
                <flux:description>{{ __('Lädt eine neue Datei hoch, um die aktuelle zu ersetzen') }}</flux:description>
                <flux:input wire:model="file" type="file" />
                <flux:description>{{ __('Maximale Dateigröße: 100MB') }}</flux:description>
                @error('file')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>

            @if ($file)
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Neue Datei:') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $file->getClientOriginalName() }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-500">{{ __('Größe:') }} {{ number_format($file->getSize() / 1024, 2) }} KB</p>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:button type="button" variant="ghost" href="{{ route('admin.downloads.index') }}" wire:navigate>
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Speichern') }}</span>
                <span wire:loading>{{ __('Wird gespeichert...') }}</span>
            </flux:button>
        </div>
    </form>
</section>

