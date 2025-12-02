<?php

use App\Models\League;
use App\Models\User;
use App\Rules\ImageDimensions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public $banner = null;
    public $logo = null;
    public ?string $discord_invite_link = null;
    public $selectedCoAdmins = null;
    public string $coAdminSearch = '';

    public function mount(): void
    {
        // Stelle sicher, dass selectedCoAdmins initial ein Array ist
        if (!is_array($this->selectedCoAdmins)) {
            $this->selectedCoAdmins = [];
        }
    }

    public function boot(): void
    {
        // Normalisiere beim Booten, falls nötig
        $this->normalizeSelectedCoAdmins();
    }

    public function updatedSelectedCoAdmins($value): void
    {
        // Normalisiere den Wert zu einem Array, sobald er aktualisiert wird
        $this->normalizeSelectedCoAdmins();
    }

    protected function normalizeSelectedCoAdmins(): void
    {
        if (!is_array($this->selectedCoAdmins)) {
            if (is_null($this->selectedCoAdmins) || $this->selectedCoAdmins === '') {
                $this->selectedCoAdmins = [];
            } else {
                $this->selectedCoAdmins = [(string) $this->selectedCoAdmins];
            }
        } else {
            // Filtere leere Werte heraus und konvertiere zu Strings
            $this->selectedCoAdmins = array_values(array_filter(array_map('strval', $this->selectedCoAdmins), fn($v) => $v !== ''));
        }
    }

    protected function getSelectedCoAdminsArray(): array
    {
        $this->normalizeSelectedCoAdmins();
        return is_array($this->selectedCoAdmins) ? $this->selectedCoAdmins : [];
    }

    public function with(): array
    {
        $users = User::query()
            ->when($this->coAdminSearch !== '', function ($query) {
                $query->where('name', 'like', '%' . $this->coAdminSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->coAdminSearch . '%');
            })
            ->limit(50)
            ->get();

        return [
            'users' => $users,
        ];
    }

    public function updatedName(): void
    {
        // Slug automatisch aus Name generieren, wenn Slug leer ist oder noch nicht manuell bearbeitet wurde
        $this->slug = Str::slug($this->name);
    }

    public function updatedSlug(): void
    {
        // Slug automatisch bereinigen, um URL-kompatibel zu bleiben
        $this->slug = Str::slug($this->slug);
    }

    protected function rules(): array
    {
        // Normalisiere selectedCoAdmins vor der Validierung
        $this->normalizeSelectedCoAdmins();
        
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:leagues,slug'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable', 'image', 'max:5120', new ImageDimensions(1152, 100)], // 1152x100px
            'logo' => ['nullable', 'image', 'max:5120', new ImageDimensions(null, null, true)], // Quadratisch
            'discord_invite_link' => ['nullable', 'url', 'max:255'],
            'selectedCoAdmins' => ['nullable', 'array'],
            'selectedCoAdmins.*' => ['exists:users,id'],
        ];
    }

    public function save(): void
    {
        // Slug automatisch aus Name generieren, falls leer
        if (empty($this->slug)) {
            $this->slug = Str::slug($this->name);
        }
        
        // Normalisiere selectedCoAdmins vor der Validierung
        $this->normalizeSelectedCoAdmins();
        
        $validated = $this->validate();
        
        $bannerPath = null;
        if ($this->banner) {
            $bannerPath = $this->banner->store('league-banners', 'public');
        }

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('league-logos', 'public');
        }

        $league = League::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'banner_path' => $bannerPath,
            'logo_path' => $logoPath,
            'discord_invite_link' => $validated['discord_invite_link'] ?? null,
            'created_by_user_id' => Auth::id(),
        ]);

        // Sync Co-Admins
        $coAdmins = $this->getSelectedCoAdminsArray();
        $league->coAdmins()->sync(array_map('intval', $coAdmins));

        $this->dispatch('notify', title: __('Liga erstellt'));

        $this->redirect(route('admin.leagues.index'), navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Neue Liga erstellen') }}</flux:heading>
        <flux:subheading>{{ __('Erstelle eine neue Liga für den Ligabetrieb') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Grundeinstellungen') }}</flux:heading>

            <div class="space-y-4">
                <flux:input
                    wire:model.live="name"
                    :label="__('Name')"
                    type="text"
                    required
                    :placeholder="__('z.B. Deutsche Dart Liga')"
                />

                <flux:input
                    wire:model="slug"
                    :label="__('URL-kompatible Kurzversion')"
                    type="text"
                    :placeholder="__('wird automatisch aus dem Namen generiert')"
                    help="{{ __('Wird automatisch generiert. Optional manuell anpassbar. Nur Kleinbuchstaben, Zahlen und Bindestriche erlaubt.') }}"
                />

                <flux:textarea
                    wire:model="description"
                    :label="__('Beschreibung')"
                    rows="3"
                    :placeholder="__('Optionale Beschreibung der Liga...')"
                />

                <flux:input
                    wire:model="discord_invite_link"
                    :label="__('Discord-Invite-Link (optional)')"
                    type="url"
                    :placeholder="__('https://discord.gg/...')"
                    help="{{ __('Optionaler Discord-Server-Link für diese Liga') }}"
                />

                <div>
                    <flux:file-upload wire:model="banner" :label="__('Banner (optional)')">
                        <flux:file-upload.dropzone 
                            heading="{{ __('Banner hochladen') }}" 
                            text="{{ __('JPG, PNG bis zu 5MB, 1152x100 Pixel') }}" 
                        />
                    </flux:file-upload>

                    @if ($banner)
                        <div class="mt-3">
                            <flux:file-item
                                :heading="$banner->getClientOriginalName()"
                                :image="$banner->temporaryUrl()"
                                :size="$banner->getSize()"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove wire:click="$set('banner', null)" aria-label="{{ __('Banner entfernen') }}" />
                                </x-slot>
                            </flux:file-item>
                        </div>
                    @endif

                    @error('banner')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:file-upload wire:model="logo" :label="__('Logo (optional)')">
                        <flux:file-upload.dropzone 
                            heading="{{ __('Logo hochladen') }}" 
                            text="{{ __('JPG, PNG bis zu 5MB, quadratisch') }}" 
                        />
                    </flux:file-upload>

                    @if ($logo)
                        <div class="mt-3">
                            <flux:file-item
                                :heading="$logo->getClientOriginalName()"
                                :image="$logo->temporaryUrl()"
                                :size="$logo->getSize()"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove wire:click="$set('logo', null)" aria-label="{{ __('Logo entfernen') }}" />
                                </x-slot>
                            </flux:file-item>
                        </div>
                    @endif

                    @error('logo')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Co-Administratoren') }}</flux:heading>

            <div class="space-y-4">
                <flux:input
                    wire:model.live.debounce.300ms="coAdminSearch"
                    :label="__('Benutzer suchen')"
                    type="text"
                    icon="magnifying-glass"
                    :placeholder="__('Name oder E-Mail eingeben...')"
                />

                <flux:pillbox
                    wire:model.live="selectedCoAdmins"
                    :label="__('Co-Administratoren')"
                    searchable
                    :placeholder="__('Co-Administratoren auswählen...')"
                    :search:placeholder="__('Benutzer suchen...')"
                >
                    @foreach ($users as $user)
                        <flux:pillbox.option value="{{ $user->id }}">
                            {{ $user->name }} ({{ $user->email }})
                        </flux:pillbox.option>
                    @endforeach
                </flux:pillbox>

                @error('selectedCoAdmins')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button
                type="button"
                variant="ghost"
                :href="route('admin.leagues.index')"
                wire:navigate
            >
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button type="submit" variant="primary">
                {{ __('Liga erstellen') }}
            </flux:button>
        </div>
    </form>
</section>
