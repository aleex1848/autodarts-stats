<?php

use App\Models\Download;
use App\Models\DownloadCategory;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $title = '';

    public ?int $categoryId = null;

    public string $description = '';

    public bool $isActive = true;

    public $file = null;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'categoryId' => ['nullable', 'exists:download_categories,id'],
            'description' => ['nullable', 'string'],
            'isActive' => ['boolean'],
            'file' => [
                'required',
                'file',
                'max:'.(100 * 1024), // 100MB in KB
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt,jpg,jpeg,png,gif,mp4,mov',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'file.required' => __('Bitte wählen Sie eine Datei aus.'),
            'file.max' => __('Die Datei darf maximal 100MB groß sein.'),
            'file.mimes' => __('Das Dateiformat wird nicht unterstützt. Erlaubte Formate: PDF, Word, Excel, PowerPoint, ZIP, RAR, TXT, Bilder, Videos.'),
        ];
    }

    public function with(): array
    {
        return [
            'categories' => DownloadCategory::query()->orderBy('name')->get(),
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $download = Download::create([
            'title' => $validated['title'],
            'category_id' => $validated['categoryId'],
            'description' => $validated['description'],
            'is_active' => $validated['isActive'],
            'created_by' => auth()->id(),
        ]);

        if ($this->file) {
            $download->addMedia($this->file->getRealPath())
                ->usingName($this->file->getClientOriginalName())
                ->usingFileName($this->file->getClientOriginalName())
                ->toMediaCollection('files');
        }

        session()->flash('success', __('Download wurde erfolgreich erstellt.'));

        $this->redirect(route('admin.downloads.index'), navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Download anlegen') }}</flux:heading>
        <flux:subheading>{{ __('Erstelle einen neuen Download') }}</flux:subheading>
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

            <flux:field>
                <flux:label>{{ __('Datei') }}</flux:label>
                <flux:input wire:model="file" type="file" />
                <flux:description>
                    {{ __('Maximale Dateigröße: 100MB') }}<br>
                    {{ __('Erlaubte Formate: PDF, Word, Excel, PowerPoint, ZIP, RAR, TXT, Bilder (JPG, PNG, GIF), Videos (MP4, MOV)') }}
                </flux:description>
                @error('file')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>

            @if ($file)
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Ausgewählte Datei:') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $file->getClientOriginalName() }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-500">{{ __('Größe:') }} {{ number_format($file->getSize() / 1024, 2) }} KB</p>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:button type="button" variant="ghost" href="{{ route('admin.downloads.index') }}" wire:navigate>
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save, file">
                <span wire:loading.remove wire:target="save, file">{{ __('Erstellen') }}</span>
                <span wire:loading wire:target="save">{{ __('Wird gespeichert...') }}</span>
                <span wire:loading wire:target="file">{{ __('Datei wird hochgeladen...') }}</span>
            </flux:button>
        </div>
    </form>
</section>

