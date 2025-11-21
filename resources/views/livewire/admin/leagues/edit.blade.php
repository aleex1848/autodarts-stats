<?php

use App\Enums\LeagueMatchFormat;
use App\Enums\LeagueMode;
use App\Enums\LeagueStatus;
use App\Enums\LeagueVariant;
use App\Models\League;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public League $league;

    public string $name = '';
    public string $description = '';
    public int $max_players = 20;
    public string $mode = '';
    public string $variant = '';
    public string $match_format = '';
    public ?string $registration_deadline = null;
    public int $days_per_matchday = 7;
    public string $status = '';

    public function mount(League $league): void
    {
        $this->league = $league;
        $this->name = $league->name;
        $this->description = $league->description ?? '';
        $this->max_players = $league->max_players;
        $this->mode = $league->mode;
        $this->variant = $league->variant;
        $this->match_format = $league->match_format;
        $this->registration_deadline = $league->registration_deadline?->format('Y-m-d\TH:i');
        $this->days_per_matchday = $league->days_per_matchday;
        $this->status = $league->status;
    }

    public function with(): array
    {
        return [
            'modes' => LeagueMode::cases(),
            'variants' => LeagueVariant::cases(),
            'formats' => LeagueMatchFormat::cases(),
            'statuses' => LeagueStatus::cases(),
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'max_players' => ['required', 'integer', 'min:2', 'max:100'],
            'mode' => ['required', 'string'],
            'variant' => ['required', 'string'],
            'match_format' => ['required', 'string'],
            'registration_deadline' => ['nullable', 'date'],
            'days_per_matchday' => ['required', 'integer', 'min:1', 'max:30'],
            'status' => ['required', 'string'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $this->league->update($validated);

        $this->dispatch('notify', title: __('Liga aktualisiert'));

        $this->redirect(route('admin.leagues.show', $this->league), navigate: true);
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
                    wire:model="name"
                    :label="__('Name')"
                    type="text"
                    required
                />

                <flux:textarea
                    wire:model="description"
                    :label="__('Beschreibung')"
                    rows="3"
                    :placeholder="__('Optionale Beschreibung der Liga...')"
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

                    <flux:input
                        wire:model="days_per_matchday"
                        :label="__('Tage pro Spieltag')"
                        type="number"
                        min="1"
                        max="30"
                        required
                    />
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
                    wire:model="variant"
                    :label="__('Spielvariante')"
                    required
                >
                    @foreach ($variants as $variantOption)
                        <option value="{{ $variantOption->value }}">
                            {{ match($variantOption->value) {
                                '501_single_single' => __('501 Single-In Single-Out'),
                                '501_single_double' => __('501 Single-In Double-Out'),
                                default => $variantOption->name
                            } }}
                        </option>
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model="match_format"
                    :label="__('Spiellänge')"
                    required
                >
                    @foreach ($formats as $format)
                        <option value="{{ $format->value }}">
                            {{ match($format->value) {
                                'best_of_3' => __('Best of 3'),
                                'best_of_5' => __('Best of 5'),
                                default => $format->name
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

