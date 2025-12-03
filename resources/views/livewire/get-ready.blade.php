<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $isIdentifying = false;
    public ?string $message = null;
    public bool $success = false;
    public ?int $playerId = null;
    public ?string $playerName = null;
    public string $autodartsName = '';
    public ?string $webhookToken = null;
    public bool $showScreenshot = false;
    public bool $showWebhookConfig = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->refreshStatus();
        
        // Reset success if player is already linked (so success message only shows once after identification)
        $user = Auth::user();
        if ($user->player && !$this->isIdentifying) {
            $this->success = false;
        }
    }

    /**
     * Generate a new webhook token (replaces existing one if it exists).
     */
    public function generateWebhookToken(): void
    {
        $user = Auth::user();
        if ($user->player) {
            return; // No need for token if player is already linked
        }
        
        $tokenName = 'Autodarts Webhook';
        
        // Delete existing token if it exists to prevent multiple unused tokens
        $user->tokens()
            ->where('name', $tokenName)
            ->delete();
        
        // Create new token
        $token = $user->createToken($tokenName);
        $this->webhookToken = $token->plainTextToken;
    }

    /**
     * Get the listeners for the component.
     */
    public function getListeners(): array
    {
        return [
            "echo-private:user." . Auth::id() . ",.player.identified" => 'handlePlayerIdentified',
        ];
    }

    /**
     * Refresh the status from the database.
     */
    public function refreshStatus(): void
    {
        $user = Auth::user();
        $this->isIdentifying = $user->is_identifying;
        $this->autodartsName = $user->autodarts_name ?? '';
        
        if ($user->player) {
            $this->playerId = $user->player->id;
            $this->playerName = $user->player->name;
        }
    }

    /**
     * Handle the player identified event from Reverb.
     */
    public function handlePlayerIdentified(array $data): void
    {
        $this->isIdentifying = $data['is_identifying'] ?? false;
        $this->success = $data['success'] ?? false;
        $this->message = $data['message'] ?? null;
        $this->playerId = $data['player_id'] ?? null;
        $this->playerName = $data['player_name'] ?? null;

        // Refresh the user to get the latest status
        Auth::user()->refresh();
        $this->refreshStatus();
        
        // Reset success after a short delay to hide success message on next page load
        if ($this->success) {
            $this->dispatch('$refresh');
        }
    }
    

    /**
     * Start the identification process.
     */
    public function startIdentification(): void
    {
        $this->validate([
            'autodartsName' => ['required', 'string', 'min:1', 'max:255'],
        ], [
            'autodartsName.required' => 'Bitte gib deinen AutoDarts-Namen ein.',
            'autodartsName.min' => 'Der AutoDarts-Name muss mindestens 1 Zeichen lang sein.',
            'autodartsName.max' => 'Der AutoDarts-Name darf maximal 255 Zeichen lang sein.',
        ]);

        $user = Auth::user();
        // Normalize: trim and replace multiple spaces with single space
        $normalizedName = preg_replace('/\s+/', ' ', trim($this->autodartsName));
        $user->update([
            'is_identifying' => true,
            'autodarts_name' => $normalizedName,
        ]);
        $this->isIdentifying = true;
        $this->message = null;
        $this->success = false;
    }

    /**
     * Cancel the identification process.
     */
    public function cancelIdentification(): void
    {
        $user = Auth::user();
        $user->update(['is_identifying' => false]);
        $this->isIdentifying = false;
        $this->message = null;
        $this->success = false;
        $this->autodartsName = $user->autodarts_name ?? '';
    }

    public function with(): array
    {
        $user = Auth::user();
        $webhookUrl = config('app.url') . '/play';
        $hasPlayer = $user->player !== null;
        
        // Only show success if player was just identified (success is true and we have a player)
        // This ensures the success message only shows once after identification
        $showSuccess = $this->success && $this->playerName !== null && $hasPlayer;
        
        return [
            'hasPlayer' => $hasPlayer,
            'webhookUrl' => $webhookUrl,
            'showSuccess' => $showSuccess,
        ];
    }
}; ?>

<div>
@if ($showSuccess && $hasPlayer && $playerName)
    <div class="relative overflow-hidden rounded-2xl border-2 border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 shadow-lg dark:border-green-800 dark:from-green-950/30 dark:to-emerald-950/30">
        <div class="flex flex-col">
            <div class="border-b border-green-200 bg-gradient-to-r from-green-100 to-emerald-100 px-8 py-5 dark:border-green-800 dark:from-green-900/30 dark:to-emerald-900/30">
                <h3 class="text-xl font-bold text-green-900 dark:text-green-100">{{ __('Spieler erfolgreich verknüpft!') }}</h3>
                <p class="mt-1 text-sm text-green-700 dark:text-green-300">{{ __('Dein AutoDarts-Spieler ist mit deinem Account verknüpft') }}</p>
            </div>

            <div class="p-8">
                <flux:callout variant="success" class="mb-4">
                    <p class="font-medium">{{ __('Erfolgreich identifiziert!') }}</p>
                    <p class="mt-2">{{ __('Dein Spieler: :playerName', ['playerName' => $playerName]) }}</p>
                </flux:callout>

                <div class="space-y-4">
                    <p class="text-neutral-700 dark:text-neutral-300">
                        {{ __('Du kannst jetzt an unseren Ligen teilnehmen.') }}
                    </p>
                    <div class="flex items-center gap-4">
                        <flux:button 
                            variant="primary" 
                            color="green"
                            :href="route('leagues.index')"
                            wire:navigate
                            icon="trophy"
                            class="font-semibold"
                        >
                            {{ __('Zu den Ligen') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@elseif (!$hasPlayer)
    <div class="relative overflow-hidden rounded-2xl border-2 border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 shadow-lg dark:border-blue-800 dark:from-blue-950/30 dark:to-indigo-950/30">
        <div class="flex flex-col">
            <div class="border-b border-blue-200 bg-gradient-to-r from-blue-100 to-indigo-100 px-8 py-5 dark:border-blue-800 dark:from-blue-900/30 dark:to-indigo-900/30">
                <h3 class="text-xl font-bold text-blue-900 dark:text-blue-100">{{ __('Get Ready') }}</h3>
                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">{{ __('Verknüpfe deinen AutoDarts-Spieler mit deinem Autodarts-Liga.de Account') }}</p>
            </div>

            <div class="p-8">
                @if($message)
                    <div class="mb-4 rounded-lg border p-3 {{ $success ? 'border-green-300 bg-green-100 dark:border-green-700 dark:bg-green-900/30' : 'border-red-300 bg-red-100 dark:border-red-700 dark:bg-red-900/30' }}">
                        <p class="text-sm font-medium {{ $success ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100' }}">
                            {{ $message }}
                        </p>
                    </div>
                @endif

                    <flux:callout class="mb-4">
                        <p class="font-medium">{{ __('So funktioniert die Identifizierung:') }}</p>
                        <ol class="mt-2 list-decimal space-y-2 ps-5 text-sm">
                            <li>
                                {{ __('Aktuelle Version Tools-For-Autodarts muss zwingend installiert sein.') }}
                                <a 
                                    href="https://github.com/creazy231/tools-for-autodarts/releases" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    class="ml-1 text-blue-600 underline hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    {{ __('Github') }}
                                </a>
                                <span class="mx-1">|</span>
                                <a 
                                    href="https://github.com/creazy231/tools-for-autodarts#-browser-extensions" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    class="text-blue-600 underline hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    {{ __('Browser Extensions') }}
                                </a>
                            </li>
                            <li>
                                {{ __('Tools öffnen und auf dem Reiter Webhooks die URL und den Token eintragen.') }}
                                <div class="mt-1 flex items-center gap-2">
                                    <flux:button 
                                        variant="primary" 
                                        size="xs"
                                        wire:click="$set('showWebhookConfig', true)"
                                    >
                                        {{ __('Meine URL/Token') }}
                                    </flux:button>
                                    @php
                                        $screenshotPath = 'tools-webhooks-example.png';
                                        $screenshotExists = \Illuminate\Support\Facades\Storage::disk('public')->exists($screenshotPath);
                                    @endphp
                                    @if($screenshotExists)
                                        <flux:button 
                                            variant="primary" 
                                            size="xs"
                                            wire:click="$set('showScreenshot', true)"
                                        >
                                            {{ __('Beispiel anzeigen') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </li>
                            <li>{{ __('Gib deinen AutoDarts-Namen ein') }}</li>
                            <li>{{ __('Klicke auf "Identifizierung starten"') }}</li>
                            <li>{{ __('Starte ein Spiel gegen einen Bot (z.B. "Bot Level 1") in Autodarts') }}</li>
                            <li>{{ __('Dein Spieler wird automatisch mit deinem Account verknüpft, wenn der Name übereinstimmt') }}</li>
                        </ol>
                    </flux:callout>

                    @if($isIdentifying)
                        <flux:callout variant="warning" class="mb-4">
                            <p class="font-medium">{{ __('Identifizierung aktiv') }}</p>
                            <p class="mt-2">{{ __('Warte auf Webhook... Bitte starte jetzt ein Spiel gegen einen Bot in Autodarts.') }}</p>
                        </flux:callout>

                        <div class="flex items-center gap-4 mb-4">
                            <flux:button variant="danger" wire:click="cancelIdentification">
                                {{ __('Identifizierung abbrechen') }}
                            </flux:button>
                        </div>
                    @endif

                    {{-- Screenshot Modal --}}
                    @if($showScreenshot)
                        @php
                            $screenshotPath = 'tools-webhooks-example.png';
                            $screenshotExists = \Illuminate\Support\Facades\Storage::disk('public')->exists($screenshotPath);
                            $screenshotUrl = $screenshotExists ? \Illuminate\Support\Facades\Storage::disk('public')->url($screenshotPath) : null;
                        @endphp
                        @if($screenshotExists && $screenshotUrl)
                            <flux:modal wire:model.self="showScreenshot" class="max-w-4xl">
                                <div class="space-y-4">
                                    <div>
                                        <flux:heading size="lg">{{ __('Tools-For-Autodarts Webhook Konfiguration') }}</flux:heading>
                                        <flux:text class="mt-2">
                                            {{ __('Trage die Webhook-URL und den Token im Reiter "Webhooks" ein.') }}
                                        </flux:text>
                                    </div>
                                    <div class="rounded-lg border border-neutral-200 bg-white p-2 dark:border-neutral-700 dark:bg-zinc-900">
                                        <img 
                                            src="{{ $screenshotUrl }}" 
                                            alt="{{ __('Tools-For-Autodarts Webhook Konfiguration') }}"
                                            class="w-full rounded-lg"
                                            loading="lazy"
                                        />
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <flux:modal.close>
                                            <flux:button variant="primary">{{ __('Schließen') }}</flux:button>
                                        </flux:modal.close>
                                    </div>
                                </div>
                            </flux:modal>
                        @endif
                    @endif

                    {{-- Webhook Konfiguration Modal --}}
                    <flux:modal wire:model.self="showWebhookConfig" class="w-full max-w-5xl">
                        <div class="space-y-4">
                            <div>
                                <flux:heading size="lg">{{ __('Webhook-Konfiguration') }}</flux:heading>
                                <flux:text class="mt-2">
                                    {{ __('Trage diese Werte in Tools-For-Autodarts ein:') }}
                                </flux:text>
                            </div>

                            <div class="space-y-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-zinc-800">
                                <div class="space-y-3">
                                    <div>
                                        <flux:label class="text-sm font-medium text-blue-900 dark:text-neutral-100">{{ __('Webhook URL') }}</flux:label>
                                        <div class="mt-1 flex items-center gap-2">
                                            <div class="min-w-0 flex-1">
                                                <flux:input 
                                                    :value="$webhookUrl" 
                                                    readonly
                                                    class="font-mono text-sm w-full"
                                                />
                                            </div>
                                            <flux:button 
                                                variant="ghost" 
                                                size="sm"
                                                icon="clipboard"
                                                x-data
                                                x-on:click="navigator.clipboard.writeText('{{ $webhookUrl }}'); $dispatch('toast', { message: '{{ __('Webhook-URL kopiert') }}', variant: 'success' })"
                                            >
                                                {{ __('Kopieren') }}
                                            </flux:button>
                                        </div>
                                    </div>

                                    <div>
                                        <flux:label class="text-sm font-medium text-blue-900 dark:text-neutral-100">{{ __('Webhook Token') }}</flux:label>
                                        @if($webhookToken)
                                            <div class="mt-1 flex items-center gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <flux:input 
                                                        :value="$webhookToken" 
                                                        readonly
                                                        class="font-mono text-sm w-full"
                                                    />
                                                </div>
                                                <flux:button 
                                                    variant="ghost" 
                                                    size="sm"
                                                    icon="clipboard"
                                                    x-data
                                                    x-on:click="navigator.clipboard.writeText('{{ $webhookToken }}'); $dispatch('toast', { message: '{{ __('Token kopiert') }}', variant: 'success' })"
                                                >
                                                    {{ __('Kopieren') }}
                                                </flux:button>
                                            </div>
                                            <flux:description class="mt-1 text-xs text-blue-600 dark:text-neutral-400">
                                                {{ __('Wird als Authorization: Bearer <Token> gesendet.') }}
                                            </flux:description>
                                        @else
                                            <div class="mt-1">
                                                <flux:button 
                                                    variant="ghost" 
                                                    size="sm"
                                                    wire:click="generateWebhookToken"
                                                    icon="key"
                                                >
                                                    {{ __('Generieren') }}
                                                </flux:button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="primary">{{ __('Schließen') }}</flux:button>
                                </flux:modal.close>
                            </div>
                        </div>
                    </flux:modal>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>{{ __('AutoDarts-Name') }}</flux:label>
                            <flux:input 
                                wire:model.live="autodartsName" 
                                placeholder="{{ __('Dein AutoDarts-Name') }}"
                                :disabled="$isIdentifying"
                            />
                            <flux:error name="autodartsName" />
                            <flux:description>
                                {{ __('Gib genau den Namen ein, der in AutoDarts angezeigt wird.') }}
                            </flux:description>
                        </flux:field>

                        <div class="flex items-center gap-4">
                            <flux:button 
                                variant="primary" 
                                color="blue"
                                wire:click="startIdentification"
                                :disabled="strlen(trim($autodartsName)) === 0 || $isIdentifying"
                                icon="link"
                                class="font-semibold"
                            >
                                {{ __('Identifizierung starten') }}
                            </flux:button>
                            <flux:button 
                                variant="ghost" 
                                :href="route('identify.edit')"
                                wire:navigate
                            >
                                {{ __('Zu den Einstellungen') }}
                            </flux:button>
                        </div>
                    </div>
            </div>
        </div>
    </div>
@endif
</div>
