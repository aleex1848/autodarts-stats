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

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->refreshStatus();
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
    }

    /**
     * Start the identification process.
     */
    public function startIdentification(): void
    {
        $user = Auth::user();
        $user->update(['is_identifying' => true]);
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
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Spieler identifizieren')" :subheading="__('Verknüpfe deinen Autodarts-Spieler mit deinem Account')">
        <div class="my-6 w-full space-y-6">
            @if($playerName)
                <flux:callout variant="success">
                    <p class="font-medium">{{ __('Dein Spieler ist bereits verknüpft:') }}</p>
                    <p>{{ $playerName }}</p>
                </flux:callout>
            @endif

            @if($isIdentifying)
                <flux:callout variant="warning">
                    <p class="font-medium">{{ __('Identifizierung aktiv') }}</p>
                    <p class="mt-2">{{ __('Warte auf Webhook... Bitte starte ein Spiel gegen einen Bot und sende einen Webhook mit deinem Sanctum Token.') }}</p>
                </flux:callout>

                <div class="flex items-center gap-4">
                    <flux:button variant="danger" wire:click="cancelIdentification">
                        {{ __('Identifizierung abbrechen') }}
                    </flux:button>
                </div>
            @elseif(!$playerName)
                <flux:callout>
                    <p class="font-medium">{{ __('So funktioniert die Identifizierung:') }}</p>
                    <ol class="mt-2 list-decimal space-y-2 ps-5">
                        <li>{{ __('Klicke auf "Identifizierung starten"') }}</li>
                        <li>{{ __('Starte ein Spiel gegen einen Bot (z.B. "Bot Level 1") in Autodarts') }}</li>
                        <li>{{ __('Sende einen Webhook mit deinem Sanctum Token an /api/webhooks') }}</li>
                        <li>{{ __('Dein Spieler wird automatisch mit deinem Account verknüpft') }}</li>
                    </ol>
                </flux:callout>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" wire:click="startIdentification">
                        {{ __('Identifizierung starten') }}
                    </flux:button>
                </div>
            @endif

            @if($message)
                <flux:callout :variant="$success ? 'success' : 'danger'">
                    <p>{{ $message }}</p>
                </flux:callout>
            @endif

            @if($playerName && !$isIdentifying)
                <div class="mt-6">
                    <flux:heading size="sm">{{ __('Verknüpfter Spieler') }}</flux:heading>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">{{ $playerName }}</p>
                </div>
            @endif
        </div>
    </x-settings.layout>
</section>
