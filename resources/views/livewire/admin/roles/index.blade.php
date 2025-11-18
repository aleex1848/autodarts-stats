<?php

use App\Enums\RoleName;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component {
    public ?int $editingRoleId = null;
    public string $name = '';
    public array $selectedPermissions = [];
    public string $newPermissionName = '';
    public bool $showRoleFormModal = false;
    public bool $showRoleDeleteModal = false;
    public ?int $roleIdBeingDeleted = null;
    public ?string $roleNameBeingDeleted = null;
    public bool $isProtectedRole = false;

    public function with(): array
    {
        return [
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->get(),
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(config('permission.table_names.roles'), 'name')->ignore($this->editingRoleId)],
            'selectedPermissions' => ['nullable', 'array'],
            'selectedPermissions.*' => ['string', Rule::exists(config('permission.table_names.permissions'), 'name')],
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetRoleForm();
        $this->showRoleFormModal = true;
    }

    public function editRole(int $roleId): void
    {
        $role = Role::with('permissions')->findOrFail($roleId);

        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->isProtectedRole = $role->name === RoleName::SuperAdmin->value;
        $this->showRoleFormModal = true;
    }

    public function saveRole(): void
    {
        $validated = $this->validate();
        $permissions = $validated['selectedPermissions'] ?? [];
        unset($validated['selectedPermissions']);

        if ($this->isProtectedRole) {
            $validated['name'] = RoleName::SuperAdmin->value;
            $permissions = Permission::query()->pluck('name')->toArray();
        }

        if ($this->editingRoleId) {
            $role = Role::findOrFail($this->editingRoleId);

            if (! $this->isProtectedRole) {
                $role->update($validated);
            }
        } else {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);
        }

        $role->syncPermissions($permissions);

        $this->showRoleFormModal = false;
        $this->resetRoleForm();

        $this->dispatch('notify', title: __('Rolle gespeichert'));
    }

    public function confirmRoleDelete(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        if ($role->name === RoleName::SuperAdmin->value) {
            $this->addError('deleteRole', __('Die Super-Admin-Rolle ist geschützt.'));

            return;
        }

        $this->roleIdBeingDeleted = $role->id;
        $this->roleNameBeingDeleted = $role->name;
        $this->showRoleDeleteModal = true;
    }

    public function deleteRole(): void
    {
        if ($this->roleIdBeingDeleted) {
            $role = Role::findOrFail($this->roleIdBeingDeleted);
            $role->delete();
        }

        $this->showRoleDeleteModal = false;
        $this->reset('roleIdBeingDeleted', 'roleNameBeingDeleted');
    }

    public function updatedShowRoleFormModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->resetRoleForm();
        }
    }

    public function updatedShowRoleDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('roleIdBeingDeleted', 'roleNameBeingDeleted');
        }
    }

    protected function resetRoleForm(): void
    {
        $this->reset('editingRoleId', 'name', 'selectedPermissions', 'isProtectedRole', 'newPermissionName');
    }

    public function addPermission(): void
    {
        $validated = $this->validate([
            'newPermissionName' => [
                'required',
                'string',
                'max:255',
                Rule::unique(config('permission.table_names.permissions'), 'name'),
            ],
        ]);

        $permission = Permission::create([
            'name' => $validated['newPermissionName'],
            'guard_name' => 'web',
        ]);

        $this->selectedPermissions = collect($this->selectedPermissions)
            ->push($permission->name)
            ->unique()
            ->values()
            ->toArray();

        $this->reset('newPermissionName');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Rollenverwaltung') }}</flux:heading>
            <flux:subheading>{{ __('Erstelle Rollen und verwalte ihre Berechtigungen') }}</flux:subheading>
        </div>

        <flux:button icon="plus" variant="primary" wire:click="openCreateModal">
            {{ __('Rolle anlegen') }}
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
                        {{ __('Berechtigungen') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Aktionen') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($roles as $role)
                    <tr wire:key="role-{{ $role->id }}">
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $role->name }}
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            @if ($role->name === \App\Enums\RoleName::SuperAdmin->value)
                                <flux:badge size="sm" variant="subtle">{{ __('Alle Berechtigungen') }}</flux:badge>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($role->permissions as $permission)
                                        <flux:badge size="sm" variant="subtle">{{ $permission->name }}</flux:badge>
                                    @empty
                                        <flux:text class="text-xs text-zinc-500">{{ __('Keine Berechtigungen') }}</flux:text>
                                    @endforelse
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($role->name === \App\Enums\RoleName::SuperAdmin->value)
                                <flux:badge size="sm" variant="success">{{ __('Geschützt') }}</flux:badge>
                            @else
                                <div class="flex justify-end gap-2">
                                    <flux:button size="xs" variant="outline" wire:click="editRole({{ $role->id }})">
                                        {{ __('Bearbeiten') }}
                                    </flux:button>
                                    <flux:button size="xs" variant="danger" wire:click="confirmRoleDelete({{ $role->id }})">
                                        {{ __('Löschen') }}
                                    </flux:button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Keine Rollen vorhanden.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal wire:model="showRoleFormModal" class="space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $editingRoleId ? __('Rolle bearbeiten') : __('Rolle anlegen') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Lege fest, welche Berechtigungen dieser Rolle zugewiesen werden.') }}
            </flux:subheading>
        </div>

        <form wire:submit="saveRole" class="space-y-4">
            <flux:input
                wire:model="name"
                :label="__('Name')"
                type="text"
                required
                :disabled="$isProtectedRole"
            />

            <flux:select
                wire:model="selectedPermissions"
                :label="__('Berechtigungen')"
                multiple
                :disabled="$isProtectedRole"
            >
                @foreach ($permissions as $permission)
                    <option value="{{ $permission->name }}">{{ $permission->name }}</option>
                @endforeach
            </flux:select>

            @unless($isProtectedRole)
                <div class="space-y-2">
                    <flux:heading size="sm">{{ __('Neue Berechtigung hinzufügen') }}</flux:heading>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <flux:input
                            wire:model="newPermissionName"
                            :label="__('Name der Berechtigung')"
                            type="text"
                            class="flex-1"
                        />

                        <flux:button
                            type="button"
                            variant="outline"
                            class="sm:mt-6 sm:w-auto"
                            wire:click="addPermission"
                        >
                            {{ __('Hinzufügen') }}
                        </flux:button>
                    </div>

                    @error('newPermissionName')
                        <flux:text class="text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
            @endunless

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showRoleFormModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ $editingRoleId ? __('Aktualisieren') : __('Anlegen') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showRoleDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Rolle löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll die Rolle ":name" dauerhaft gelöscht werden?', ['name' => $roleNameBeingDeleted]) }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showRoleDeleteModal', false)">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button variant="danger" wire:click="deleteRole">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>
</section>

