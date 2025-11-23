<?php

use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public string $search = '';

    public function with(): array
    {
        return [
            'users' => User::query()
                ->with(['roles'])
                ->when($this->search !== '', function ($query) {
                    $search = '%'.$this->search.'%';

                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
                })
                ->orderBy('name')
                ->get(),
            'currentUserId' => auth()->id(),
            'isInSwitchMode' => session()->has('original_user_id'),
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('User-Switch') }}</flux:heading>
            <flux:subheading>{{ __('Als ein anderer Benutzer einloggen') }}</flux:subheading>
        </div>

        @if (session()->has('original_user_id'))
            <flux:badge variant="solid" size="lg" class="bg-amber-500 text-white">
                {{ __('Switch-Modus aktiv') }}
            </flux:badge>
        @endif
    </div>

    <flux:field>
        <flux:input
            wire:model.live.debounce.300ms="search"
            type="search"
            icon="magnifying-glass"
            :placeholder="__('Benutzer suchen...')"
        />
    </flux:field>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Name') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('E-Mail') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Rollen') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Aktion') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $user->name }}
                            @if ($user->id === $currentUserId)
                                <flux:badge size="sm" variant="subtle" class="ml-2">{{ __('Sie') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $user->email }}
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($user->roles as $role)
                                    <flux:badge size="sm" variant="subtle">{{ $role->name }}</flux:badge>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            @if ($user->id !== $currentUserId)
                                <form method="POST" action="{{ route('admin.user-switch.switch', $user) }}">
                                    @csrf
                                    <flux:button type="submit" size="xs" variant="primary">
                                        {{ __('Switch') }}
                                    </flux:button>
                                </form>
                            @else
                                <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Aktueller Benutzer') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Keine Benutzer gefunden.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-circle">
            {{ session('error') }}
        </flux:callout>
    @endif
</section>
