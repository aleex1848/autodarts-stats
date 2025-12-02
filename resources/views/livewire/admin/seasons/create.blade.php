<?php

use App\Enums\LeagueMatchFormat;
use App\Enums\LeagueMode;
use App\Enums\LeagueStatus;
use App\Enums\LeagueVariant;
use App\Enums\MatchdayScheduleMode;
use App\Models\League;
use App\Models\Season;
use App\Models\User;
use App\Rules\ImageDimensions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?int $league_id = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public int $max_players = 20;
    public string $mode = '';
    public string $variant = '';
    public string $match_format = '';
    public ?string $registration_deadline = null;
    public int $days_per_matchday = 7;
    public string $matchday_schedule_mode = '';
    public string $status = '';
    public $banner = null;
    public $logo = null;
    public string $dashboard_display_type = 'none';
    public ?string $dashboard_badge_color = null;
    public $selectedCoAdmins = [];
    public string $coAdminSearch = '';
    
    // X01 Game Settings
    public ?int $base_score = 501;
    public ?string $in_mode = 'Straight';
    public ?string $out_mode = 'Double';
    public ?string $bull_mode = '25/50';
    public ?int $max_rounds = 50;
    public ?string $bull_off = 'Normal';
    public ?string $match_mode_type = 'Legs';
    public ?int $match_mode_legs_count = 5;
    public ?int $match_mode_sets_count = null;

    public function mount(?int $league = null): void
    {
        $this->league_id = $league;
        $this->mode = LeagueMode::SingleRound->value;
        $this->variant = LeagueVariant::Single501DoubleOut->value;
        $this->match_format = LeagueMatchFormat::BestOf3->value;
        $this->matchday_schedule_mode = MatchdayScheduleMode::Timed->value;
        $this->status = LeagueStatus::Registration->value;
        // Set default X01 settings based on variant and match_format
        $this->base_score = 501;
        $this->in_mode = 'Straight';
        $this->out_mode = 'Double';
        $this->bull_mode = '25/50';
        $this->max_rounds = 50;
        $this->bull_off = 'Normal';
        $this->match_mode_type = 'Legs';
        $this->match_mode_legs_count = 5;
        $this->match_mode_sets_count = null;
        $this->normalizeSelectedCoAdmins();
    }

    public function hydrate(): void
    {
        // Normalisiere nach dem Hydratisieren, falls Livewire einen String zugewiesen hat
        $this->normalizeSelectedCoAdmins();
    }

    protected function normalizeSelectedCoAdmins(): void
    {
        if (!is_array($this->selectedCoAdmins)) {
            if (is_null($this->selectedCoAdmins) || $this->selectedCoAdmins === '') {
                $this->selectedCoAdmins = [];
            } else {
                // Konvertiere zu String-Array, da Pillbox Strings sendet
                $this->selectedCoAdmins = [(string) $this->selectedCoAdmins];
            }
        } else {
            // Filtere leere Werte heraus und konvertiere zu Strings
            $this->selectedCoAdmins = array_values(
                array_filter(
                    array_map('strval', $this->selectedCoAdmins),
                    fn($v) => $v !== '' && $v !== '0'
                )
            );
        }
    }

    public function with(): array
    {
        $leagues = League::query()
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->when($this->coAdminSearch !== '', function ($query) {
                $query->where('name', 'like', '%' . $this->coAdminSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->coAdminSearch . '%');
            })
            ->limit(50)
            ->get();

        return [
            'leagues' => $leagues,
            'modes' => LeagueMode::cases(),
            'variants' => LeagueVariant::cases(),
            'formats' => LeagueMatchFormat::cases(),
            'statuses' => LeagueStatus::cases(),
            'scheduleModes' => MatchdayScheduleMode::cases(),
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
        // Normalisiere den Wert zu einem Array, sobald er aktualisiert wird
        $this->normalizeSelectedCoAdmins();
    }

    protected function rules(): array
    {
        return [
            'league_id' => ['required', 'exists:leagues,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string'],
            'max_players' => ['required', 'integer', 'min:2', 'max:100'],
            'mode' => ['required', 'string'],
            'variant' => ['required', 'string'],
            'match_format' => ['required', 'string'],
            'base_score' => ['nullable', 'integer', 'in:121,170,301,501,701,901'],
            'in_mode' => ['nullable', 'string', 'in:Straight,Double,Master'],
            'out_mode' => ['nullable', 'string', 'in:Straight,Double,Master'],
            'bull_mode' => ['nullable', 'string', 'in:25/50,50/50'],
            'max_rounds' => ['nullable', 'integer', 'in:15,20,50,80'],
            'bull_off' => ['nullable', 'string', 'in:Off,Normal,Official'],
            'match_mode_type' => ['nullable', 'string', 'in:Off,Legs,Sets'],
            'match_mode_legs_count' => [
                'nullable',
                'required_if:match_mode_type,Legs',
                'required_if:match_mode_type,Sets',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($this->match_mode_type === 'Legs' && ($value < 1 || $value > 11)) {
                        $fail(__('Die Anzahl der Legs muss zwischen 1 und 11 liegen.'));
                    }
                    if ($this->match_mode_type === 'Sets' && !in_array($value, [2, 3])) {
                        $fail(__('Bei Sets-Modus muss die Anzahl der Legs 2 oder 3 sein.'));
                    }
                },
            ],
            'match_mode_sets_count' => ['nullable', 'required_if:match_mode_type,Sets', 'integer', 'min:2', 'max:7'],
            'registration_deadline' => ['nullable', 'date', 'after:now'],
            'days_per_matchday' => ['required_if:matchday_schedule_mode,timed', 'nullable', 'integer', 'min:1', 'max:30'],
            'matchday_schedule_mode' => ['required', 'string'],
            'status' => ['required', 'string'],
            'banner' => ['nullable', 'image', 'max:5120', new ImageDimensions(1152, 100)], // 1152x100px Banner
            'logo' => ['nullable', 'image', 'max:5120', new ImageDimensions(null, null, true)], // Quadratisch
            'dashboard_display_type' => ['required', 'string', 'in:none,banner,logo'],
            'dashboard_badge_color' => ['nullable', 'required_if:dashboard_display_type,banner', 'string', 'in:zinc,red,orange,amber,yellow,lime,green,emerald,teal,cyan,sky,blue,indigo,violet,purple,fuchsia,pink,rose'],
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
        
        // Prüfe, ob Slug innerhalb der Liga eindeutig ist
        $existingSeason = Season::where('league_id', $validated['league_id'])
            ->where('slug', $validated['slug'])
            ->exists();

        if ($existingSeason) {
            $this->addError('slug', __('Dieser Slug existiert bereits für eine Saison in dieser Liga.'));
            
            return;
        }
        
        $bannerPath = null;
        if ($this->banner) {
            $bannerPath = $this->banner->store('season-banners', 'public');
        }

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('season-logos', 'public');
        }

        $season = Season::create([
            'league_id' => $validated['league_id'],
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'max_players' => $validated['max_players'],
            'mode' => $validated['mode'],
            'variant' => $validated['variant'],
            'match_format' => $validated['match_format'],
            'registration_deadline' => $validated['registration_deadline'] ? now()->parse($validated['registration_deadline']) : null,
            'days_per_matchday' => $validated['days_per_matchday'] ?? null,
            'matchday_schedule_mode' => $validated['matchday_schedule_mode'],
            'status' => $validated['status'],
            'banner_path' => $bannerPath,
            'logo_path' => $logoPath,
            'dashboard_display_type' => $validated['dashboard_display_type'],
            'dashboard_badge_color' => $validated['dashboard_badge_color'] ?? null,
            'created_by_user_id' => Auth::id(),
            'base_score' => $validated['base_score'] ?? null,
            'in_mode' => $validated['in_mode'] ?? null,
            'out_mode' => $validated['out_mode'] ?? null,
            'bull_mode' => $validated['bull_mode'] ?? null,
            'max_rounds' => $validated['max_rounds'] ?? null,
            'bull_off' => $validated['bull_off'] ?? null,
            'match_mode_type' => $validated['match_mode_type'] ?? null,
            'match_mode_legs_count' => $validated['match_mode_legs_count'] ?? null,
            'match_mode_sets_count' => $validated['match_mode_sets_count'] ?? null,
        ]);

        // Sync Co-Admins - konvertiere zu Integer-Array für die Datenbank
        $coAdmins = !empty($validated['selectedCoAdmins']) 
            ? array_map('intval', $validated['selectedCoAdmins']) 
            : [];
        $season->coAdmins()->sync($coAdmins);

        $this->dispatch('notify', title: __('Saison erstellt'));

        $this->redirect(route('admin.seasons.show', $season), navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Neue Saison erstellen') }}</flux:heading>
        <flux:subheading>{{ __('Erstelle eine neue Saison für eine Liga') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Grundeinstellungen') }}</flux:heading>

            <div class="space-y-4">
                <flux:select
                    wire:model="league_id"
                    :label="__('Liga')"
                    required
                >
                    <option value="">{{ __('Liga auswählen...') }}</option>
                    @foreach ($leagues as $league)
                        <option value="{{ $league->id }}">{{ $league->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model.live="name"
                    :label="__('Name')"
                    type="text"
                    required
                    :placeholder="__('z.B. Saison 2025')"
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
                    :placeholder="__('Optionale Beschreibung der Saison...')"
                />

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input
                        wire:model="max_players"
                        :label="__('Maximale Spielerzahl')"
                        type="number"
                        min="2"
                        max="100"
                        required
                    />

                    @if ($matchday_schedule_mode === 'timed')
                        <flux:input
                            wire:model="days_per_matchday"
                            :label="__('Tage pro Spieltag')"
                            type="number"
                            min="1"
                            max="30"
                            required
                        />
                    @endif
                </div>

                <flux:input
                    wire:model="registration_deadline"
                    :label="__('Anmeldeschluss (optional)')"
                    type="datetime-local"
                />
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Spieleinstellungen') }}</flux:heading>

            <div class="space-y-4">
                <flux:select
                    wire:model.live="matchday_schedule_mode"
                    :label="__('Spieltag-Planung')"
                    required
                >
                    @foreach ($scheduleModes as $scheduleMode)
                        <option value="{{ $scheduleMode->value }}">
                            {{ match($scheduleMode->value) {
                                'timed' => __('Zeitlich begrenzt (Ein Spieltag pro X Tage)'),
                                'unlimited_no_order' => __('Ohne Zeitlimit, ohne Reihenfolge'),
                                'unlimited_with_order' => __('Ohne Zeitlimit, mit Reihenfolge'),
                                default => $scheduleMode->name
                            } }}
                        </option>
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model="mode"
                    :label="__('Modus')"
                    required
                >
                    @foreach ($modes as $modeOption)
                        <option value="{{ $modeOption->value }}">
                            {{ match($modeOption->value) {
                                'single_round' => __('Nur Hinrunde'),
                                'double_round' => __('Hin & Rückrunde'),
                                default => $modeOption->name
                            } }}
                        </option>
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model="status"
                    :label="__('Status')"
                    required
                >
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}">
                            {{ __(ucfirst($statusOption->name)) }}
                        </option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('X01 Spieleinstellungen') }}</flux:heading>

            <div class="space-y-6">
                <div>
                    <flux:radio.group wire:model="base_score" variant="buttons" class="w-full *:flex-1" :label="__('Base Score')">
                        @foreach ([121, 170, 301, 501, 701, 901] as $score)
                            <flux:radio :value="$score">{{ $score }}</flux:radio>
                        @endforeach
                    </flux:radio.group>
                    @error('base_score')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:radio.group wire:model="in_mode" variant="buttons" class="w-full *:flex-1" :label="__('In Mode')">
                        @foreach (['Straight', 'Double', 'Master'] as $mode)
                            <flux:radio :value="$mode">{{ $mode }}</flux:radio>
                        @endforeach
                    </flux:radio.group>
                    @error('in_mode')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:radio.group wire:model="out_mode" variant="buttons" class="w-full *:flex-1" :label="__('Out Mode')">
                        @foreach (['Straight', 'Double', 'Master'] as $mode)
                            <flux:radio :value="$mode">{{ $mode }}</flux:radio>
                        @endforeach
                    </flux:radio.group>
                    @error('out_mode')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:radio.group wire:model="max_rounds" variant="buttons" class="w-full *:flex-1" :label="__('Max Rounds')">
                        @foreach ([15, 20, 50, 80] as $rounds)
                            <flux:radio :value="$rounds">{{ $rounds }}</flux:radio>
                        @endforeach
                    </flux:radio.group>
                    @error('max_rounds')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:radio.group wire:model="bull_mode" variant="buttons" class="w-full *:flex-1" :label="__('Bull Mode')">
                        @foreach (['25/50', '50/50'] as $bullMode)
                            <flux:radio :value="$bullMode">{{ $bullMode }}</flux:radio>
                        @endforeach
                    </flux:radio.group>
                    @error('bull_mode')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:radio.group wire:model="bull_off" variant="buttons" class="w-full *:flex-1" :label="__('Bull-Off')">
                        @foreach (['Off', 'Normal', 'Official'] as $bullOff)
                            <flux:radio :value="$bullOff">{{ $bullOff }}</flux:radio>
                        @endforeach
                    </flux:radio.group>
                    @error('bull_off')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:radio.group wire:model.live="match_mode_type" variant="buttons" class="w-full *:flex-1" :label="__('Match Mode')">
                        @foreach (['Off', 'Legs', 'Sets'] as $matchMode)
                            <flux:radio :value="$matchMode">{{ $matchMode }}</flux:radio>
                        @endforeach
                    </flux:radio.group>
                    @error('match_mode_type')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    @if ($match_mode_type === 'Legs')
                        <div class="mt-3">
                            <flux:select wire:model="match_mode_legs_count" :label="__('First to X legs')">
                                @foreach (range(1, 11) as $legs)
                                    <option value="{{ $legs }}">{{ __('First to :count leg', ['count' => $legs]) }}</option>
                                @endforeach
                            </flux:select>
                            @error('match_mode_legs_count')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    @if ($match_mode_type === 'Sets')
                        <div class="mt-3 space-y-3">
                            <flux:select wire:model="match_mode_sets_count" :label="__('First to X sets')">
                                @foreach (range(2, 7) as $sets)
                                    <option value="{{ $sets }}">{{ __('First to :count sets', ['count' => $sets]) }}</option>
                                @endforeach
                            </flux:select>
                            @error('match_mode_sets_count')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            <flux:select wire:model="match_mode_legs_count" :label="__('First to X legs')">
                                @foreach ([2, 3] as $legs)
                                    <option value="{{ $legs }}">{{ __('First to :count', ['count' => $legs]) }}</option>
                                @endforeach
                            </flux:select>
                            @error('match_mode_legs_count')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Bilder') }}</flux:heading>

            <div class="space-y-4">
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
            <flux:heading size="lg" class="mb-4">{{ __('Dashboard "Dein Spieltag" Einstellungen') }}</flux:heading>

            <div class="space-y-4">
                <flux:select
                    wire:model.live="dashboard_display_type"
                    :label="__('Anzeigetyp')"
                    required
                >
                    <option value="none">{{ __('Keine') }}</option>
                    <option value="banner">{{ __('Banner') }}</option>
                    <option value="logo">{{ __('Logo') }}</option>
                </flux:select>

                @if ($dashboard_display_type === 'banner')
                    <flux:select
                        wire:model="dashboard_badge_color"
                        :label="__('Badge-Farbe')"
                        required
                    >
                        <option value="">{{ __('Farbe auswählen...') }}</option>
                        <option value="zinc">Zinc</option>
                        <option value="red">Red</option>
                        <option value="orange">Orange</option>
                        <option value="amber">Amber</option>
                        <option value="yellow">Yellow</option>
                        <option value="lime">Lime</option>
                        <option value="green">Green</option>
                        <option value="emerald">Emerald</option>
                        <option value="teal">Teal</option>
                        <option value="cyan">Cyan</option>
                        <option value="sky">Sky</option>
                        <option value="blue">Blue</option>
                        <option value="indigo">Indigo</option>
                        <option value="violet">Violet</option>
                        <option value="purple">Purple</option>
                        <option value="fuchsia">Fuchsia</option>
                        <option value="pink">Pink</option>
                        <option value="rose">Rose</option>
                    </flux:select>
                @endif

                @error('dashboard_display_type')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('dashboard_badge_color')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
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
                    multiple
                    :label="__('Co-Administratoren')"
                    searchable
                    :placeholder="__('Co-Administratoren auswählen...')"
                    :search:placeholder="__('Benutzer suchen...')"
                >
                    @foreach ($users as $user)
                        <flux:pillbox.option value="{{ (string) $user->id }}">
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
                {{ __('Saison erstellen') }}
            </flux:button>
        </div>
    </form>
</section>
