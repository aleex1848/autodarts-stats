<?php

use App\Models\Header;
use App\Models\FrontpageLogo;
use App\Services\SettingsService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $activeTab = 'headers';
    
    // Dashboard settings properties
    public int $latestMatchesCount = 5;
    public int $runningMatchesCount = 5;
    public int $upcomingMatchesCount = 5;
    public int $platformNewsCount = 5;
    public int $leagueNewsCount = 5;
    
    // Header properties
    public ?int $editingHeaderId = null;
    public string $headerName = '';
    public bool $headerIsActive = false;
    public $headerLogo = null;
    public bool $showHeaderFormModal = false;
    public bool $showHeaderDeleteModal = false;
    public ?int $headerIdBeingDeleted = null;
    public ?string $headerNameBeingDeleted = null;
    
    // FrontpageLogo properties
    public ?int $editingFrontpageLogoId = null;
    public string $frontpageLogoName = '';
    public bool $frontpageLogoIsActive = false;
    public $frontpageLogoImage = null;
    public bool $showFrontpageLogoFormModal = false;
    public bool $showFrontpageLogoDeleteModal = false;
    public ?int $frontpageLogoIdBeingDeleted = null;
    public ?string $frontpageLogoNameBeingDeleted = null;

    public function mount(): void
    {
        $this->latestMatchesCount = SettingsService::getLatestMatchesCount();
        $this->runningMatchesCount = SettingsService::getRunningMatchesCount();
        $this->upcomingMatchesCount = SettingsService::getUpcomingMatchesCount();
        $this->platformNewsCount = SettingsService::getPlatformNewsCount();
        $this->leagueNewsCount = SettingsService::getLeagueNewsCount();
    }

    public function with(): array
    {
        return [
            'headers' => Header::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'frontpageLogos' => FrontpageLogo::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ];
    }

    public function saveDashboardSettings(): void
    {
        $validated = $this->validate([
            'latestMatchesCount' => ['required', 'integer', 'min:1', 'max:100'],
            'runningMatchesCount' => ['required', 'integer', 'min:1', 'max:100'],
            'upcomingMatchesCount' => ['required', 'integer', 'min:1', 'max:100'],
            'platformNewsCount' => ['required', 'integer', 'min:1', 'max:100'],
            'leagueNewsCount' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        SettingsService::setLatestMatchesCount($validated['latestMatchesCount']);
        SettingsService::setRunningMatchesCount($validated['runningMatchesCount']);
        SettingsService::setUpcomingMatchesCount($validated['upcomingMatchesCount']);
        SettingsService::setPlatformNewsCount($validated['platformNewsCount']);
        SettingsService::setLeagueNewsCount($validated['leagueNewsCount']);

        $this->dispatch('notify', title: __('Dashboard-Einstellungen gespeichert'));
    }

    protected function headerRules(): array
    {
        return [
            'headerName' => ['required', 'string', 'max:255'],
            'headerIsActive' => ['boolean'],
            'headerLogo' => [
                $this->editingHeaderId ? 'nullable' : 'nullable',
                'image',
                'max:10240', // 10MB
            ],
        ];
    }

    protected function frontpageLogoRules(): array
    {
        return [
            'frontpageLogoName' => ['required', 'string', 'max:255'],
            'frontpageLogoIsActive' => ['boolean'],
            'frontpageLogoImage' => [
                $this->editingFrontpageLogoId ? 'nullable' : 'nullable',
                'image',
                'max:10240', // 10MB
            ],
        ];
    }

    // Header methods
    public function openCreateHeaderModal(): void
    {
        $this->resetHeaderForm();
        $this->showHeaderFormModal = true;
    }

    public function editHeader(int $headerId): void
    {
        $header = Header::findOrFail($headerId);

        $this->editingHeaderId = $header->id;
        $this->headerName = $header->name;
        $this->headerIsActive = $header->is_active;
        $this->headerLogo = null;
        $this->showHeaderFormModal = true;
    }

    public function saveHeader(): void
    {
        $validated = $this->validate($this->headerRules());

        if ($this->editingHeaderId) {
            $header = Header::findOrFail($this->editingHeaderId);
            $header->update([
                'name' => $validated['headerName'],
                'is_active' => $validated['headerIsActive'],
            ]);
        } else {
            $header = Header::create([
                'name' => $validated['headerName'],
                'is_active' => $validated['headerIsActive'],
            ]);
        }

        if ($this->headerLogo) {
            $header->clearMediaCollection('header');
            $header->addMedia($this->headerLogo->getRealPath())
                ->usingName($this->headerLogo->getClientOriginalName())
                ->usingFileName($this->headerLogo->getClientOriginalName())
                ->withResponsiveImages()
                ->toMediaCollection('header');
        }

        $this->showHeaderFormModal = false;
        $this->resetHeaderForm();

        $this->dispatch('notify', title: __('Header gespeichert'));
    }

    public function confirmDeleteHeader(int $headerId): void
    {
        $header = Header::findOrFail($headerId);

        $this->headerIdBeingDeleted = $header->id;
        $this->headerNameBeingDeleted = $header->name;
        $this->showHeaderDeleteModal = true;
    }

    public function deleteHeader(): void
    {
        if ($this->headerIdBeingDeleted) {
            $header = Header::findOrFail($this->headerIdBeingDeleted);
            $header->delete();

            $this->dispatch('notify', title: __('Header gelöscht'));
        }

        $this->showHeaderDeleteModal = false;
        $this->headerIdBeingDeleted = null;
        $this->headerNameBeingDeleted = null;
    }

    public function updatedShowHeaderFormModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->resetHeaderForm();
        }
    }

    public function updatedShowHeaderDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('headerIdBeingDeleted', 'headerNameBeingDeleted');
        }
    }

    protected function resetHeaderForm(): void
    {
        $this->reset('editingHeaderId', 'headerName', 'headerIsActive', 'headerLogo');
    }

    // FrontpageLogo methods
    public function openCreateFrontpageLogoModal(): void
    {
        $this->resetFrontpageLogoForm();
        $this->showFrontpageLogoFormModal = true;
    }

    public function editFrontpageLogo(int $frontpageLogoId): void
    {
        $frontpageLogo = FrontpageLogo::findOrFail($frontpageLogoId);

        $this->editingFrontpageLogoId = $frontpageLogo->id;
        $this->frontpageLogoName = $frontpageLogo->name;
        $this->frontpageLogoIsActive = $frontpageLogo->is_active;
        $this->frontpageLogoImage = null;
        $this->showFrontpageLogoFormModal = true;
    }

    public function saveFrontpageLogo(): void
    {
        $validated = $this->validate($this->frontpageLogoRules());

        if ($this->editingFrontpageLogoId) {
            $frontpageLogo = FrontpageLogo::findOrFail($this->editingFrontpageLogoId);
            $frontpageLogo->update([
                'name' => $validated['frontpageLogoName'],
                'is_active' => $validated['frontpageLogoIsActive'],
            ]);
        } else {
            $frontpageLogo = FrontpageLogo::create([
                'name' => $validated['frontpageLogoName'],
                'is_active' => $validated['frontpageLogoIsActive'],
            ]);
        }

        if ($this->frontpageLogoImage) {
            $frontpageLogo->clearMediaCollection('frontpage-logo');
            $frontpageLogo->addMedia($this->frontpageLogoImage->getRealPath())
                ->usingName($this->frontpageLogoImage->getClientOriginalName())
                ->usingFileName($this->frontpageLogoImage->getClientOriginalName())
                ->withResponsiveImages()
                ->toMediaCollection('frontpage-logo');
        }

        $this->showFrontpageLogoFormModal = false;
        $this->resetFrontpageLogoForm();

        $this->dispatch('notify', title: __('Frontpage Logo gespeichert'));
    }

    public function confirmDeleteFrontpageLogo(int $frontpageLogoId): void
    {
        $frontpageLogo = FrontpageLogo::findOrFail($frontpageLogoId);

        $this->frontpageLogoIdBeingDeleted = $frontpageLogo->id;
        $this->frontpageLogoNameBeingDeleted = $frontpageLogo->name;
        $this->showFrontpageLogoDeleteModal = true;
    }

    public function deleteFrontpageLogo(): void
    {
        if ($this->frontpageLogoIdBeingDeleted) {
            $frontpageLogo = FrontpageLogo::findOrFail($this->frontpageLogoIdBeingDeleted);
            $frontpageLogo->delete();

            $this->dispatch('notify', title: __('Frontpage Logo gelöscht'));
        }

        $this->showFrontpageLogoDeleteModal = false;
        $this->frontpageLogoIdBeingDeleted = null;
        $this->frontpageLogoNameBeingDeleted = null;
    }

    public function updatedShowFrontpageLogoFormModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->resetFrontpageLogoForm();
        }
    }

    public function updatedShowFrontpageLogoDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('frontpageLogoIdBeingDeleted', 'frontpageLogoNameBeingDeleted');
        }
    }

    protected function resetFrontpageLogoForm(): void
    {
        $this->reset('editingFrontpageLogoId', 'frontpageLogoName', 'frontpageLogoIsActive', 'frontpageLogoImage');
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Page Settings') }}</flux:heading>
        <flux:subheading>{{ __('Verwalte seitenweite Einstellungen wie Header und Branding') }}</flux:subheading>
    </div>

    <x-page-settings.layout :heading="__('Dashboard')" :subheading="__('Verwalte Dashboard-Einstellungen und Media-Konfigurationen')">
        <div class="space-y-6">
            <!-- Dashboard Settings -->
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="md" class="mb-4">{{ __('Match-Anzeige Einstellungen') }}</flux:heading>
                <flux:subheading class="mb-6">{{ __('Konfiguriere die Anzahl der anzuzeigenden Matches im Dashboard') }}</flux:subheading>

                <form wire:submit="saveDashboardSettings" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="latestMatchesCount"
                            type="number"
                            :label="__('Anzahl Latest Matches')"
                            min="1"
                            max="100"
                            required
                        />
                        <flux:input
                            wire:model="runningMatchesCount"
                            type="number"
                            :label="__('Anzahl Running Matches')"
                            min="1"
                            max="100"
                            required
                        />
                        <flux:input
                            wire:model="upcomingMatchesCount"
                            type="number"
                            :label="__('Anzahl Upcoming Matches')"
                            min="1"
                            max="100"
                            required
                        />
                    </div>

                    <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                        <flux:heading size="sm" class="mb-4">{{ __('News-Einstellungen') }}</flux:heading>
                        <flux:subheading class="mb-4">{{ __('Konfiguriere die Anzahl der anzuzeigenden News im Dashboard') }}</flux:subheading>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <flux:input
                                wire:model="platformNewsCount"
                                type="number"
                                :label="__('Anzahl Platform News')"
                                min="1"
                                max="100"
                                required
                            />
                            <flux:input
                                wire:model="leagueNewsCount"
                                type="number"
                                :label="__('Anzahl Liga News')"
                                min="1"
                                max="100"
                                required
                            />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveDashboardSettings">{{ __('Speichern') }}</span>
                            <span wire:loading wire:target="saveDashboardSettings">{{ __('Wird gespeichert...') }}</span>
                        </flux:button>
                    </div>
                </form>
            </div>

            <!-- Media Verwaltung -->
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="md" class="mb-4">{{ __('Media Verwaltung') }}</flux:heading>
                <flux:subheading class="mb-6">{{ __('Verwalte Header und Frontpage Logo Konfigurationen') }}</flux:subheading>

            <!-- Tabs -->
            <flux:tabs wire:model="activeTab">
                <flux:tab name="headers">{{ __('Header') }}</flux:tab>
                <flux:tab name="frontpage-logos">{{ __('Frontpage Logo') }}</flux:tab>
            </flux:tabs>

            <!-- Headers Tab Content -->
            <div x-show="$wire.activeTab === 'headers'" class="space-y-6">
                <div class="flex justify-end">
                    <flux:button icon="plus" variant="primary" wire:click="openCreateHeaderModal">
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
                                    {{ __('Bild') }}
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
                                            <span class="text-zinc-400 dark:text-zinc-500">{{ __('Kein Bild') }}</span>
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
                                                wire:click="confirmDeleteHeader({{ $header->id }})"
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

            <!-- Frontpage Logos Tab Content -->
            <div x-show="$wire.activeTab === 'frontpage-logos'" class="space-y-6">
                <div class="flex justify-end">
                    <flux:button icon="plus" variant="primary" wire:click="openCreateFrontpageLogoModal">
                        {{ __('Frontpage Logo erstellen') }}
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
                            @forelse ($frontpageLogos as $frontpageLogo)
                                <tr wire:key="frontpage-logo-{{ $frontpageLogo->id }}">
                                    <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $frontpageLogo->name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                        @if($frontpageLogo->hasMedia('frontpage-logo'))
                                            <img src="{{ $frontpageLogo->getFirstMediaUrl('frontpage-logo') }}" alt="{{ $frontpageLogo->name }}" class="h-12 w-auto rounded">
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">{{ __('Kein Logo') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                        <flux:badge
                                            size="sm"
                                            :variant="$frontpageLogo->is_active ? 'success' : 'subtle'"
                                        >
                                            {{ $frontpageLogo->is_active ? __('Aktiv') : __('Inaktiv') }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <div class="flex justify-end gap-2">
                                            <flux:button
                                                size="xs"
                                                variant="outline"
                                                wire:click="editFrontpageLogo({{ $frontpageLogo->id }})"
                                            >
                                                {{ __('Bearbeiten') }}
                                            </flux:button>

                                            <flux:button
                                                size="xs"
                                                variant="danger"
                                                wire:click="confirmDeleteFrontpageLogo({{ $frontpageLogo->id }})"
                                            >
                                                {{ __('Löschen') }}
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Keine Frontpage Logos vorhanden.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
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
            <flux:input wire:model="headerName" :label="__('Name')" type="text" required />

            <flux:switch wire:model="headerIsActive" :label="__('Aktiv')" />

            <flux:field>
                <flux:label>{{ __('Bild') }}</flux:label>
                <flux:input wire:model="headerLogo" type="file" accept="image/*" />
                <flux:description>
                    {{ __('Maximale Dateigröße: 10MB') }}<br>
                    {{ __('Erlaubte Formate: JPG, PNG, GIF, SVG') }}
                </flux:description>
                @error('headerLogo')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>

            @if ($headerLogo)
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Ausgewählte Datei:') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $headerLogo->getClientOriginalName() }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-500">{{ __('Größe:') }} {{ number_format($headerLogo->getSize() / 1024, 2) }} KB</p>
                </div>
            @endif

            @if ($editingHeaderId)
                @php
                    $editingHeader = Header::find($editingHeaderId);
                @endphp
                @if($editingHeader && $editingHeader->hasMedia('header'))
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                        <p class="mb-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Aktuelles Bild:') }}</p>
                        <img src="{{ $editingHeader->getFirstMediaUrl('header') }}" alt="{{ $editingHeader->name }}" class="h-24 w-auto rounded">
                    </div>
                @endif
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showHeaderFormModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveHeader, headerLogo">
                    <span wire:loading.remove wire:target="saveHeader, headerLogo">{{ $editingHeaderId ? __('Aktualisieren') : __('Erstellen') }}</span>
                    <span wire:loading wire:target="saveHeader">{{ __('Wird gespeichert...') }}</span>
                    <span wire:loading wire:target="headerLogo">{{ __('Bild wird hochgeladen...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Header Delete Confirmation Modal -->
    <flux:modal wire:model="showHeaderDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Header löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll der Header ":name" wirklich gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden.', ['name' => $headerNameBeingDeleted]) }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showHeaderDeleteModal', false)">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button variant="danger" wire:click="deleteHeader">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>

    <!-- Frontpage Logo Form Modal -->
    <flux:modal wire:model="showFrontpageLogoFormModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingFrontpageLogoId ? __('Frontpage Logo bearbeiten') : __('Frontpage Logo erstellen') }}</flux:heading>
            <flux:subheading>{{ $editingFrontpageLogoId ? __('Bearbeite die Frontpage Logo-Konfiguration') : __('Erstelle ein neues Frontpage Logo') }}</flux:subheading>
        </div>

        <form wire:submit="saveFrontpageLogo" class="space-y-4">
            <flux:input wire:model="frontpageLogoName" :label="__('Name')" type="text" required />

            <flux:switch wire:model="frontpageLogoIsActive" :label="__('Aktiv')" />

            <flux:field>
                <flux:label>{{ __('Logo') }}</flux:label>
                <flux:input wire:model="frontpageLogoImage" type="file" accept="image/*" />
                <flux:description>
                    {{ __('Maximale Dateigröße: 10MB') }}<br>
                    {{ __('Erlaubte Formate: JPG, PNG, GIF, SVG') }}
                </flux:description>
                @error('frontpageLogoImage')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>

            @if ($frontpageLogoImage)
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Ausgewählte Datei:') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $frontpageLogoImage->getClientOriginalName() }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-500">{{ __('Größe:') }} {{ number_format($frontpageLogoImage->getSize() / 1024, 2) }} KB</p>
                </div>
            @endif

            @if ($editingFrontpageLogoId)
                @php
                    $editingFrontpageLogo = FrontpageLogo::find($editingFrontpageLogoId);
                @endphp
                @if($editingFrontpageLogo && $editingFrontpageLogo->hasMedia('frontpage-logo'))
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                        <p class="mb-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Aktuelles Logo:') }}</p>
                        <img src="{{ $editingFrontpageLogo->getFirstMediaUrl('frontpage-logo') }}" alt="{{ $editingFrontpageLogo->name }}" class="h-24 w-auto rounded">
                    </div>
                @endif
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showFrontpageLogoFormModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveFrontpageLogo, frontpageLogoImage">
                    <span wire:loading.remove wire:target="saveFrontpageLogo, frontpageLogoImage">{{ $editingFrontpageLogoId ? __('Aktualisieren') : __('Erstellen') }}</span>
                    <span wire:loading wire:target="saveFrontpageLogo">{{ __('Wird gespeichert...') }}</span>
                    <span wire:loading wire:target="frontpageLogoImage">{{ __('Logo wird hochgeladen...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Frontpage Logo Delete Confirmation Modal -->
    <flux:modal wire:model="showFrontpageLogoDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Frontpage Logo löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll das Frontpage Logo ":name" wirklich gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden.', ['name' => $frontpageLogoNameBeingDeleted]) }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showFrontpageLogoDeleteModal', false)">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button variant="danger" wire:click="deleteFrontpageLogo">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>
</section>
