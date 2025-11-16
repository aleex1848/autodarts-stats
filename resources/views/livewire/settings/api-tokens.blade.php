<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $tokenName = '';
    public ?string $plainTextToken = null;
    public $tokens = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadTokens();
    }

    /**
     * Load all tokens for the current user.
     */
    public function loadTokens(): void
    {
        $user = Auth::user();
        
        if ($user) {
            $this->tokens = $user->tokens()->orderBy('created_at', 'desc')->get();
        }
    }

    /**
     * Create a new API token.
     */
    public function createToken(): void
    {
        $validated = $this->validate([
            'tokenName' => ['required', 'string', 'max:255'],
        ]);

        $token = Auth::user()->createToken($validated['tokenName']);

        $this->plainTextToken = $token->plainTextToken;
        $this->tokenName = '';
        $this->loadTokens();
    }

    /**
     * Delete an API token.
     */
    public function deleteToken(int $tokenId): void
    {
        Auth::user()->tokens()->where('id', $tokenId)->delete();

        $this->loadTokens();
    }

    /**
     * Close the token display modal.
     */
    public function closeTokenModal(): void
    {
        $this->plainTextToken = null;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('API Tokens')" :subheading="__('Manage your API tokens for external integrations')">
        <!-- Create New Token Form -->
        <form wire:submit="createToken" class="my-6 w-full space-y-6">
            <flux:heading size="lg">{{ __('Create New Token') }}</flux:heading>
            
            <flux:input 
                wire:model="tokenName" 
                :label="__('Token Name')" 
                type="text" 
                required 
                autofocus 
                :placeholder="__('e.g., Mobile App, Third Party Integration')"
            />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="create-token-button">
                    {{ __('Create Token') }}
                </flux:button>
            </div>
        </form>

        <flux:separator class="my-8" />

        <!-- Existing Tokens List -->
        <div class="my-6 w-full space-y-6">
            <flux:heading size="lg">{{ __('Your API Tokens') }}</flux:heading>

            @if (count($tokens) > 0)
                <div class="space-y-3">
                    @foreach ($tokens as $token)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex-1">
                                <flux:text class="font-semibold">{{ $token->name }}</flux:text>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Created') }}: {{ $token->created_at->format('d.m.Y H:i') }}
                                    @if ($token->last_used_at)
                                        · {{ __('Last used') }}: {{ $token->last_used_at->diffForHumans() }}
                                    @else
                                        · {{ __('Never used') }}
                                    @endif
                                </flux:text>
                            </div>
                            <flux:button 
                                variant="danger" 
                                size="sm"
                                wire:click="deleteToken({{ $token->id }})"
                                wire:confirm="{{ __('Are you sure you want to delete this token? This action cannot be undone.') }}"
                                data-test="delete-token-button-{{ $token->id }}"
                            >
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('You have not created any API tokens yet.') }}
                </flux:text>
            @endif
        </div>
    </x-settings.layout>

    <!-- Token Display Modal -->
    @if ($plainTextToken)
        <flux:modal wire:model="plainTextToken" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('API Token Created') }}</flux:heading>
                <flux:subheading>
                    {{ __('Please copy your new API token. For your security, it won\'t be shown again.') }}
                </flux:subheading>
            </div>

            <div class="rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800">
                <flux:text class="break-all font-mono text-sm">{{ $plainTextToken }}</flux:text>
            </div>

            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ __('Make sure to copy your API token now. You won\'t be able to see it again!') }}
            </flux:callout>

            <div class="flex justify-end gap-2">
                <flux:button 
                    variant="primary" 
                    wire:click="closeTokenModal"
                    data-test="close-token-modal-button"
                >
                    {{ __('I\'ve saved my token') }}
                </flux:button>
            </div>
        </flux:modal>
    @endif
</section>

