<?php

use App\Models\OpenAISetting;
use App\Services\OpenAIService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $model = 'o1-preview';
    public int $maxTokens = 2000;
    public int $maxCompletionTokens = 4000;
    public string $matchPrompt = '';
    public string $matchdayPrompt = '';
    public string $seasonPrompt = '';
    public array $availableModels = [];
    public bool $isLoadingModels = false;
    public ?string $modelsError = null;

    public function mount(): void
    {
        $settings = OpenAISetting::getCurrent();
        $this->model = $settings->model;
        $this->maxTokens = $settings->max_tokens ?? 2000;
        $this->maxCompletionTokens = $settings->max_completion_tokens ?? 4000;
        $this->matchPrompt = $settings->match_prompt ?? '';
        $this->matchdayPrompt = $settings->matchday_prompt ?? '';
        $this->seasonPrompt = $settings->season_prompt ?? '';
        $this->loadAvailableModels();
    }

    public function loadAvailableModels(): void
    {
        $this->isLoadingModels = true;
        $this->modelsError = null;

        try {
            $openAIService = app(OpenAIService::class);
            $this->availableModels = $openAIService->getAvailableModels();
        } catch (\Exception $e) {
            $this->modelsError = $e->getMessage();
            // Fallback to config
            $this->availableModels = config('openai.available_models', []);
        } finally {
            $this->isLoadingModels = false;
        }
    }

    protected function rules(): array
    {
        $availableModelKeys = array_keys($this->availableModels);

        return [
            'model' => ['required', 'string', 'in:' . implode(',', $availableModelKeys)],
            'maxTokens' => ['required', 'integer', 'min:100', 'max:32000'],
            'maxCompletionTokens' => ['required', 'integer', 'min:100', 'max:32000'],
            'matchPrompt' => ['required', 'string', 'min:50'],
            'matchdayPrompt' => ['required', 'string', 'min:50'],
            'seasonPrompt' => ['required', 'string', 'min:50'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $settings = OpenAISetting::getCurrent();
        $settings->update([
            'model' => $validated['model'],
            'max_tokens' => $validated['maxTokens'],
            'max_completion_tokens' => $validated['maxCompletionTokens'],
            'match_prompt' => $validated['matchPrompt'],
            'matchday_prompt' => $validated['matchdayPrompt'],
            'season_prompt' => $validated['seasonPrompt'],
        ]);

        $this->dispatch('notify', title: __('OpenAI-Einstellungen gespeichert'));
    }
}; ?>

<x-page-settings.layout :heading="__('OpenAI-Einstellungen')" :subheading="__('Konfiguriere das Standard-Modell für alle OpenAI-Operationen')">
    <div class="space-y-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <form wire:submit="save" class="space-y-6">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:label>{{ __('Standard OpenAI-Modell') }}</flux:label>
                        <flux:button 
                            type="button" 
                            size="xs" 
                            variant="ghost" 
                            icon="arrow-path"
                            wire:click="loadAvailableModels"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="loadAvailableModels">
                                {{ __('Aktualisieren') }}
                            </span>
                            <span wire:loading wire:target="loadAvailableModels">
                                {{ __('Lädt...') }}
                            </span>
                        </flux:button>
                    </div>
                    
                    @if ($isLoadingModels && empty($availableModels))
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Lade verfügbare Modelle...') }}
                        </div>
                    @elseif ($modelsError)
                        <flux:callout variant="danger" size="sm">
                            {{ __('Fehler beim Laden der Modelle: :error', ['error' => $modelsError]) }}
                        </flux:callout>
                    @else
                        <flux:select wire:model="model" :placeholder="__('Modell auswählen')">
                            @foreach ($availableModels as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>

                <flux:description>
                    {{ __('Das ausgewählte Modell wird für alle automatisch generierten News-Artikel verwendet.') }}
                </flux:description>

                <flux:input
                    wire:model="maxTokens"
                    type="number"
                    :label="__('Max Tokens (für GPT-4, GPT-3.5)')"
                    min="100"
                    max="32000"
                    required
                />
                <flux:description>
                    {{ __('Maximale Anzahl von Tokens für die Antwort bei GPT-4 und GPT-3.5 Modellen.') }}
                </flux:description>

                <flux:input
                    wire:model="maxCompletionTokens"
                    type="number"
                    :label="__('Max Completion Tokens (für GPT-5, O1)')"
                    min="100"
                    max="32000"
                    required
                />
                <flux:description>
                    {{ __('Maximale Anzahl von Completion-Tokens für GPT-5 und O1-Modelle. Diese Modelle verwenden Reasoning-Tokens, daher sollte dieser Wert höher sein (empfohlen: 4000-8000).') }}
                </flux:description>

                <flux:textarea
                    wire:model="matchPrompt"
                    :label="__('Prompt für Spielberichte')"
                    rows="15"
                    required
                />
                <flux:description>
                    {{ __('Der Prompt-Template für die Generierung von Spielberichten. Verfügbare Platzhalter: {league_name}, {season_name}, {matchday_number}, {match_format}, {home_player}, {away_player}, {winner}, {player_stats}, {match_progression}, {highlights}') }}
                </flux:description>

                <flux:textarea
                    wire:model="matchdayPrompt"
                    :label="__('Prompt für Spieltagsberichte')"
                    rows="15"
                    required
                />
                <flux:description>
                    {{ __('Der Prompt-Template für die Generierung von Spieltagsberichten. Verfügbare Platzhalter: {league_name}, {season_name}, {matchday_number}, {total_fixtures}, {completed_fixtures}, {match_results}, {highlights}, {table_changes}') }}
                </flux:description>

                <flux:textarea
                    wire:model="seasonPrompt"
                    :label="__('Prompt für Saisonberichte')"
                    rows="15"
                    required
                />
                <flux:description>
                    {{ __('Der Prompt-Template für die Generierung von Saisonberichten. Verfügbare Platzhalter: {league_name}, {season_name}, {total_matchdays}, {completed_matchdays}, {season_results}, {final_standings}, {highlights}, {champion}') }}
                </flux:description>

                <div class="flex justify-end gap-2">
                    <flux:button type="submit" variant="primary">
                        {{ __('Speichern') }}
                    </flux:button>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="md" class="mb-4">{{ __('Verfügbare Modelle') }}</flux:heading>
            @if (empty($availableModels))
                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Keine Modelle verfügbar. Bitte API-Key konfigurieren oder Modelle aktualisieren.') }}
                </div>
            @else
                <div class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <p>{{ __('Die folgenden Modelle wurden von der OpenAI API geladen:') }}</p>
                    <ul class="list-disc list-inside space-y-1 mt-2">
                        @foreach ($availableModels as $value => $label)
                            <li><strong>{{ $label }}</strong> ({{ $value }})</li>
                        @endforeach
                    </ul>
                    <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Die Modelle werden für 24 Stunden gecacht. Klicken Sie auf "Aktualisieren", um die neuesten Modelle von der OpenAI API zu laden.') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-page-settings.layout>

