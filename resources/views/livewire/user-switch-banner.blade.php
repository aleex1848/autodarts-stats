<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $originalUserId = session('original_user_id');
        $originalUser = $originalUserId ? User::find($originalUserId) : null;
        $currentUser = auth()->user();

        return [
            'isInSwitchMode' => session()->has('original_user_id'),
            'originalUser' => $originalUser,
            'currentUser' => $currentUser,
        ];
    }

    public function stopSwitch(): void
    {
        $originalUserId = session('original_user_id');

        if (! $originalUserId) {
            return;
        }

        $originalUser = User::findOrFail($originalUserId);

        // Remove the session key
        session()->forget('original_user_id');

        // Login back as the original user
        Auth::login($originalUser);

        // Redirect to dashboard
        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<span>
    @if ($isInSwitchMode && $originalUser)
        <flux:tooltip :content="__('User-Switch aktiv: Eingeloggt als :current (Original: :original)', ['current' => $currentUser->name, 'original' => $originalUser->name])" position="bottom">
            <flux:button 
                wire:click="stopSwitch" 
                variant="ghost" 
                square 
                size="sm"
                class="relative text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300"
                aria-label="{{ __('User-Switch beenden') }}"
            >
                <flux:icon.arrows-right-left variant="mini" />
                <span class="absolute -right-1 -top-1 flex size-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-amber-500"></span>
                </span>
            </flux:button>
        </flux:tooltip>
    @endif
</span>
