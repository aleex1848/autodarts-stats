<?php

use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public League $league;

    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public $banner = null;
    public ?string $discord_invite_link = null;
    public array $selectedCoAdmins = [];
    public string $coAdminSearch = '';

    public function mount(League $league): void
    {
        $this->league = $league->load('coAdmins');
        $this->name = $league->name;
        $this->slug = $league->slug ?? '';
        $this->description = $league->description ?? '';
        $this->discord_invite_link = $league->discord_invite_link;
        $this->selectedCoAdmins = $league->coAdmins->pluck('id')->toArray();
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

    public function updatedSelectedCoAdmins($value): void
    {
        // Stelle sicher, dass selectedCoAdmins immer ein Array ist
        if (is_null($value) || $value === '') {
            $this->selectedCoAdmins = [];
        } elseif (!is_array($value)) {
            $this->selectedCoAdmins = [(string) $value];
        } else {
            // Filtere leere Werte heraus und konvertiere zu Strings
            $this->selectedCoAdmins = array_values(array_filter(array_map('strval', $value), fn($v) => $v !== ''));
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:leagues,slug,' . $this->league->id],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable', 'image', 'max:5120'], // 5MB max
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
        
        $validated = $this->validate();
        
        $bannerPath = $this->league->banner_path;
        if ($this->banner) {
            // Altes Banner löschen, falls vorhanden
            if ($bannerPath && Storage::disk('public')->exists($bannerPath)) {
                Storage::disk('public')->delete($bannerPath);
            }
            $bannerPath = $this->banner->store('league-banners', 'public');
        }

        $this->league->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'banner_path' => $bannerPath,
            'discord_invite_link' => $validated['discord_invite_link'] ?? null,
        ]);

        // Sync Co-Admins
        $this->league->coAdmins()->sync($validated['selectedCoAdmins'] ?? []);

        $this->dispatch('notify', title: __('Liga aktualisiert'));

        $this->redirect(route('admin.leagues.show', $this->league), navigate: true);
    }

    public function removeBanner(): void
    {
        if ($this->league->banner_path && Storage::disk('public')->exists($this->league->banner_path)) {
            Storage::disk('public')->delete($this->league->banner_path);
        }
        
        $this->league->update(['banner_path' => null]);
        $this->banner = null;
        
        $this->dispatch('notify', title: __('Banner entfernt'));
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Liga bearbeiten') }}</flux:heading>
        <flux:subheading>{{ __('Bearbeite die Einstellungen der Liga') }}</flux:subheading>
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
                            text="{{ __('JPG, PNG bis zu 5MB') }}" 
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
                    @elseif ($league->banner_path)
                        <div class="mt-3">
                            <div class="relative">
                                <img src="{{ Storage::url($league->banner_path) }}" alt="{{ $league->name }}" class="h-32 w-auto rounded-lg object-cover" />
                                <flux:button
                                    type="button"
                                    size="xs"
                                    variant="danger"
                                    class="absolute right-2 top-2"
                                    wire:click="removeBanner"
                                >
                                    {{ __('Entfernen') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    @error('banner')
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
                    wire:model="selectedCoAdmins"
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
                :href="route('admin.leagues.show', $league)"
                wire:navigate
            >
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button type="submit" variant="primary">
                {{ __('Speichern') }}
            </flux:button>
        </div>
    </form>
</section>
