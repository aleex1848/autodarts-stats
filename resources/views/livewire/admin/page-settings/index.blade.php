<?php

use App\Models\Header;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?int $editingHeaderId = null;
    public string $name = '';
    public bool $isActive = false;
    public $logo = null;
    public bool $showHeaderFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $headerIdBeingDeleted = null;
    public ?string $headerNameBeingDeleted = null;

    public function with(): array
    {
        return [
            'headers' => Header::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'logo' => [
                $this->editingHeaderId ? 'nullable' : 'nullable',
                'image',
                'max:10240', // 10MB
            ],
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showHeaderFormModal = true;
    }

    public function editHeader(int $headerId): void
    {
        $header = Header::findOrFail($headerId);

        $this->editingHeaderId = $header->id;
        $this->name = $header->name;
        $this->isActive = $header->is_active;
        $this->logo = null;
        $this->showHeaderFormModal = true;
    }

    public function saveHeader(): void
    {
        $validated = $this->validate();

        if ($this->editingHeaderId) {
            $header = Header::findOrFail($this->editingHeaderId);
            $header->update([
                'name' => $validated['name'],
                'is_active' => $validated['isActive'],
            ]);
        } else {
            $header = Header::create([
                'name' => $validated['name'],
                'is_active' => $validated['isActive'],
            ]);
        }

        if ($this->logo) {
            $header->clearMediaCollection('header');
            $header->addMedia($this->logo->getRealPath())
                ->usingName($this->logo->getClientOriginalName())
                ->usingFileName($this->logo->getClientOriginalName())
                ->toMediaCollection('header');
        }

        $this->showHeaderFormModal = false;
        $this->resetForm();

        $this->dispatch('notify', title: __('Header gespeichert'));
    }

    public function confirmDelete(int $headerId): void
    {
        $header = Header::findOrFail($headerId);

        $this->headerIdBeingDeleted = $header->id;
        $this->headerNameBeingDeleted = $header->name;
        $this->showDeleteModal = true;
    }

    public function deleteHeader(): void
    {
        if ($this->headerIdBeingDeleted) {
            $header = Header::findOrFail($this->headerIdBeingDeleted);
            $header->delete();

            $this->dispatch('notify', title: __('Header gelöscht'));
        }

        $this->showDeleteModal = false;
        $this->headerIdBeingDeleted = null;
        $this->headerNameBeingDeleted = null;
    }

    public function updatedShowHeaderFormModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->resetForm();
        }
    }

    public function updatedShowDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('headerIdBeingDeleted', 'headerNameBeingDeleted');
        }
    }

    protected function resetForm(): void
    {
        $this->reset('editingHeaderId', 'name', 'isActive', 'logo');
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Page Settings') }}</flux:heading>
        <flux:subheading>{{ __('Verwalte seitenweite Einstellungen wie Header und Branding') }}</flux:subheading>
    </div>

    <x-page-settings.layout :heading="__('Header Verwaltung')" :subheading="__('Verwalte Header-Konfigurationen und Logos')">
        <div class="space-y-6">
            <div class="flex justify-end">
                <flux:button icon="plus" variant="primary" wire:click="openCreateModal">
                    {{ __('Header erstellen') }}
                </flux:button>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                {{ __('Name') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                {{ __('Logo') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                {{ __('Status') }}
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                {{ __('Aktionen') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($headers as $header)
                            <tr wire:key="header-{{ $header->id }}">
                                <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $header->name }}
                                </td>
                                <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                    @if($header->hasMedia('header'))
                                        <img src="{{ $header->getFirstMediaUrl('header') }}" alt="{{ $header->name }}" class="h-12 w-auto rounded">
                                    @else
                                        <span class="text-zinc-400 dark:text-zinc-500">{{ __('Kein Logo') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                    <flux:badge
                                        size="sm"
                                        :variant="$header->is_active ? 'success' : 'subtle'"
                                    >
                                        {{ $header->is_active ? __('Aktiv') : __('Inaktiv') }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <div class="flex justify-end gap-2">
                                        <flux:button
                                            size="xs"
                                            variant="outline"
                                            wire:click="editHeader({{ $header->id }})"
                                        >
                                            {{ __('Bearbeiten') }}
                                        </flux:button>

                                        <flux:button
                                            size="xs"
                                            variant="danger"
                                            wire:click="confirmDelete({{ $header->id }})"
                                        >
                                            {{ __('Löschen') }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Keine Header vorhanden.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-page-settings.layout>

    <!-- Header Form Modal -->
    <flux:modal wire:model="showHeaderFormModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingHeaderId ? __('Header bearbeiten') : __('Header erstellen') }}</flux:heading>
            <flux:subheading>{{ $editingHeaderId ? __('Bearbeite die Header-Konfiguration') : __('Erstelle einen neuen Header') }}</flux:subheading>
        </div>

        <form wire:submit="saveHeader" class="space-y-4">
            <flux:input wire:model="name" :label="__('Name')" type="text" required />

            <flux:switch wire:model="isActive" :label="__('Aktiv')" />

            <flux:field>
                <flux:label>{{ __('Logo') }}</flux:label>
                <flux:input wire:model="logo" type="file" accept="image/*" />
                <flux:description>
                    {{ __('Maximale Dateigröße: 10MB') }}<br>
                    {{ __('Erlaubte Formate: JPG, PNG, GIF, SVG') }}
                </flux:description>
                @error('logo')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>

            @if ($logo)
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Ausgewählte Datei:') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $logo->getClientOriginalName() }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-500">{{ __('Größe:') }} {{ number_format($logo->getSize() / 1024, 2) }} KB</p>
                </div>
            @endif

            @if ($editingHeaderId)
                @php
                    $editingHeader = Header::find($editingHeaderId);
                @endphp
                @if($editingHeader && $editingHeader->hasMedia('header'))
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                        <p class="mb-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Aktuelles Logo:') }}</p>
                        <img src="{{ $editingHeader->getFirstMediaUrl('header') }}" alt="{{ $editingHeader->name }}" class="h-24 w-auto rounded">
                    </div>
                @endif
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showHeaderFormModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveHeader, logo">
                    <span wire:loading.remove wire:target="saveHeader, logo">{{ $editingHeaderId ? __('Aktualisieren') : __('Erstellen') }}</span>
                    <span wire:loading wire:target="saveHeader">{{ __('Wird gespeichert...') }}</span>
                    <span wire:loading wire:target="logo">{{ __('Logo wird hochgeladen...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="showDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Header löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll der Header ":name" wirklich gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden.', ['name' => $headerNameBeingDeleted]) }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button variant="danger" wire:click="deleteHeader">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>
</section>
