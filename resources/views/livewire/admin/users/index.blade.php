<?php

use App\Models\Player;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new class extends Component {
    public ?int $editingUserId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $discordUsername = '';
    public string $discordId = '';
    public array $selectedRoles = [];
    public ?int $selectedPlayerId = null;
    public string $playerSearch = '';
    public bool $showUserFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $userIdBeingDeleted = null;
    public ?string $userNameBeingDeleted = null;

    public function with(): array
    {
        return [
            'users' => User::query()->with(['roles', 'player'])->orderBy('name')->get(),
            'availableRoles' => Role::query()->orderBy('name')->get(),
            'players' => Player::query()
                ->where(function ($query) {
                    // Show unlinked players
                    $query->whereNull('user_id');

                    // Also show the currently linked player when editing
                    if ($this->editingUserId) {
                        $query->orWhere('user_id', $this->editingUserId);
                    }
                    
                    // Also include the selected player if one is selected (for display purposes)
                    if ($this->selectedPlayerId) {
                        $query->orWhere('id', $this->selectedPlayerId);
                    }
                })
                ->orderBy('name')
                ->limit(100)
                ->get(),
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->editingUserId),
            ],
            'password' => [$this->editingUserId ? 'nullable' : 'required', 'string', 'min:8'],
            'discordUsername' => ['nullable', 'string', 'max:255'],
            'discordId' => ['nullable', 'string', 'max:255'],
            'selectedRoles' => ['required', 'array', 'min:1'],
            'selectedRoles.*' => ['string', Rule::exists(config('permission.table_names.roles'), 'name')],
            'selectedPlayerId' => [
                'nullable',
                'integer',
                Rule::exists(Player::class, 'id'),
            ],
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showUserFormModal = true;
    }

    public function editUser(int $userId): void
    {
        $user = User::with(['roles', 'player'])->findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->discordUsername = $user->discord_username ?? '';
        $this->discordId = $user->discord_id ?? '';
        $this->selectedRoles = $user->roles->pluck('name')->toArray();
        $this->selectedPlayerId = $user->player?->id;
        $this->playerSearch = '';
        $this->showUserFormModal = true;
    }

    public function saveUser(): void
    {
        $validated = $this->validate();
        $roles = $validated['selectedRoles'];
        unset($validated['selectedRoles']);

        // Map discordUsername to discord_username for database
        if (isset($validated['discordUsername'])) {
            $validated['discord_username'] = $validated['discordUsername'] ?: null;
            unset($validated['discordUsername']);
        }

        // Map discordId to discord_id for database
        if (isset($validated['discordId'])) {
            $validated['discord_id'] = $validated['discordId'] ?: null;
            unset($validated['discordId']);
        }

        if ($this->editingUserId) {
            $user = User::findOrFail($this->editingUserId);

            if ($validated['password'] === '') {
                unset($validated['password']);
            }

            $user->update($validated);
        } else {
            $user = User::create($validated);
        }

        $user->syncRoles($roles);

        $currentPlayer = $user->player;

        if ($this->selectedPlayerId) {
            if (! $currentPlayer || $currentPlayer->id !== $this->selectedPlayerId) {
                // Unlink current player if exists
                if ($currentPlayer) {
                    $currentPlayer->update(['user_id' => null]);
                }

                // Get the selected player and unlink it from any other user first
                $player = Player::findOrFail($this->selectedPlayerId);
                if ($player->user_id && $player->user_id !== $user->id) {
                    // Player is linked to another user, unlink it first
                    $player->update(['user_id' => null]);
                }
                
                // Link the player to this user
                $player->update(['user_id' => $user->id]);
            }
        } elseif ($currentPlayer) {
            // Remove player link if no player is selected
            $currentPlayer->update(['user_id' => null]);
        }

        $this->showUserFormModal = false;
        $this->resetForm();

        $this->dispatch('notify', title: __('Benutzer gespeichert'));
    }

    public function confirmDelete(int $userId): void
    {
        if ($userId === auth()->id()) {
            $this->addError('delete', __('Du kannst deinen eigenen Benutzer nicht löschen.'));

            return;
        }

        $user = User::findOrFail($userId);

        $this->userIdBeingDeleted = $user->id;
        $this->userNameBeingDeleted = $user->name;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        if ($this->userIdBeingDeleted) {
            $user = User::findOrFail($this->userIdBeingDeleted);
            $user->delete();
        }

        $this->showDeleteModal = false;
        $this->userIdBeingDeleted = null;
        $this->userNameBeingDeleted = null;
    }

    public function updatedShowUserFormModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->resetForm();
        }
    }

    public function updatedShowDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('userIdBeingDeleted', 'userNameBeingDeleted');
        }
    }

    public function updatedSelectedPlayerId($playerId): void
    {
        // Handle empty string from clearable select
        if ($playerId === '' || $playerId === null) {
            $this->selectedPlayerId = null;
        } else {
            $this->selectedPlayerId = (int) $playerId;
        }
    }

    protected function resetForm(): void
    {
        $this->reset('editingUserId', 'name', 'email', 'password', 'discordUsername', 'discordId', 'selectedRoles', 'selectedPlayerId', 'playerSearch');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Benutzerverwaltung') }}</flux:heading>
            <flux:subheading>{{ __('Verwalte alle Benutzer und ihre Rollen') }}</flux:subheading>
        </div>

        <flux:button icon="plus" variant="primary" wire:click="openCreateModal">
            {{ __('Benutzer anlegen') }}
        </flux:button>
    </div>

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
                        {{ __('Player') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Rollen') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Aktionen') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $user->name }}
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $user->email }}
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            @if ($user->player)
                                <flux:badge size="sm" variant="subtle">
                                    {{ $user->player->name }}
                                </flux:badge>
                            @else
                                <span class="text-zinc-400 dark:text-zinc-500">{{ __('Kein Player') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($user->roles as $role)
                                    <flux:badge size="sm" variant="subtle">{{ $role->name }}</flux:badge>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" variant="outline" wire:click="editUser({{ $user->id }})">
                                    {{ __('Bearbeiten') }}
                                </flux:button>

                                <flux:button
                                    size="xs"
                                    variant="danger"
                                    wire:click="confirmDelete({{ $user->id }})"
                                    :disabled="auth()->id() === $user->id"
                                >
                                    {{ __('Löschen') }}
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Keine Benutzer vorhanden.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal wire:model="showUserFormModal" class="space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $editingUserId ? __('Benutzer bearbeiten') : __('Benutzer anlegen') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Pflege Name, E-Mail, Passwort und Rollen des Benutzers') }}
            </flux:subheading>
        </div>

        <form wire:submit="saveUser" class="space-y-4">
            <flux:input wire:model="name" :label="__('Name')" type="text" required />
            <flux:input wire:model="email" :label="__('E-Mail')" type="email" required />
            <flux:input wire:model="discordUsername" :label="__('Discord Username (optional)')" type="text" autocomplete="username" />
            <flux:input wire:model="discordId" :label="__('Discord User ID (optional)')" type="text" autocomplete="off" />
            <flux:input
                wire:model="password"
                :label="$editingUserId ? __('Neues Passwort (optional)') : __('Passwort')"
                type="password"
                :required="! $editingUserId"
                autocomplete="new-password"
            />

            <flux:select
                wire:model="selectedPlayerId"
                variant="listbox"
                searchable
                clearable
                :label="__('Verknüpfter Player')"
                :placeholder="__('Player suchen...')"
            >
                @forelse ($players as $player)
                    <flux:select.option value="{{ $player->id }}">
                        {{ $player->name ?? __('Player #:id', ['id' => $player->id]) }}
                        @if ($player->email)
                            ({{ $player->email }})
                        @endif
                    </flux:select.option>
                @empty
                    <flux:select.option value="" disabled>
                        {{ __('Keine passenden Player gefunden') }}
                    </flux:select.option>
                @endforelse
            </flux:select>

            <flux:pillbox
                wire:model="selectedRoles"
                :label="__('Rollen')"
                searchable
                :placeholder="__('Rollen auswählen...')"
                :search:placeholder="__('Rollen suchen...')"
            >
                @foreach ($availableRoles as $role)
                    <flux:pillbox.option value="{{ $role->name }}">
                        {{ $role->name }}
                    </flux:pillbox.option>
                @endforeach
            </flux:pillbox>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showUserFormModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ $editingUserId ? __('Aktualisieren') : __('Anlegen') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Benutzer löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll der Benutzer ":name" wirklich entfernt werden? Diese Aktion kann nicht rückgängig gemacht werden.', ['name' => $userNameBeingDeleted]) }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button variant="danger" wire:click="deleteUser">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>
</section>

