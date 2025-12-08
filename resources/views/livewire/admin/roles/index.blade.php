<?php

use App\Enums\RoleName;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component {
    use WithPagination;

    // Role properties
    public ?int $editingRoleId = null;
    public string $name = '';
    public array $selectedPermissions = [];
    public bool $showRoleFormModal = false;
    public bool $showRoleDeleteModal = false;
    public ?int $roleIdBeingDeleted = null;
    public ?string $roleNameBeingDeleted = null;
    public bool $isProtectedRole = false;

    // Permission properties
    public ?int $editingPermissionId = null;
    public string $permissionName = '';
    public string $permissionSearch = '';
    public bool $showPermissionFormModal = false;
    public bool $showPermissionDeleteModal = false;
    public ?int $permissionIdBeingDeleted = null;
    public ?string $permissionNameBeingDeleted = null;

    // Tab state
    public string $activeTab = 'roles';

    protected $queryString = [
        'permissionSearch' => ['except' => ''],
        'page' => ['except' => 1],
        'activeTab' => ['except' => 'roles'],
    ];

    public function updatingPermissionSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedPermissions($value): void
    {
        // Normalisiere den Wert zu einem Array
        $this->normalizeSelectedPermissions();
    }

    protected function normalizeSelectedPermissions(): void
    {
        if (! is_array($this->selectedPermissions)) {
            if (is_null($this->selectedPermissions) || $this->selectedPermissions === '') {
                $this->selectedPermissions = [];
            } else {
                // Konvertiere zu String-Array, da Pillbox Strings sendet
                $value = (string) $this->selectedPermissions;
                $this->selectedPermissions = $value !== '' ? [$value] : [];
            }
        } else {
            // Filtere leere Werte heraus und konvertiere zu Strings
            $this->selectedPermissions = array_values(
                array_filter(
                    array_map('strval', $this->selectedPermissions),
                    fn($v) => $v !== '' && $v !== null && $v !== '0'
                )
            );
        }
    }

    public function with(): array
    {
        $permissionsQuery = Permission::query()->with('roles');

        if ($this->permissionSearch !== '') {
            $permissionsQuery->where('name', 'like', '%' . $this->permissionSearch . '%');
        }

        return [
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'permissions' => $permissionsQuery->orderBy('name')->paginate(15),
            'allPermissions' => Permission::query()->orderBy('name')->get(),
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(config('permission.table_names.roles'), 'name')->ignore($this->editingRoleId)],
            'permissionName' => ['required', 'string', 'max:255', Rule::unique(config('permission.table_names.permissions'), 'name')->ignore($this->editingPermissionId)],
        ];
    }

    // Role methods
    public function openCreateModal(): void
    {
        $this->resetRoleForm();
        // Stelle sicher, dass selectedPermissions ein Array ist
        if (! is_array($this->selectedPermissions)) {
            $this->selectedPermissions = [];
        }
        $this->showRoleFormModal = true;
    }

    public function editRole(int $roleId): void
    {
        $role = Role::with('permissions')->findOrFail($roleId);

        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->values()->toArray();
        $this->normalizeSelectedPermissions();
        $this->isProtectedRole = $role->name === RoleName::SuperAdmin->value;
        $this->showRoleFormModal = true;
    }

    public function saveRole(): void
    {
        // Stelle sicher, dass selectedPermissions immer ein Array ist
        if (! is_array($this->selectedPermissions)) {
            $this->selectedPermissions = [];
        }
        
        // Normalisiere selectedPermissions
        $this->normalizeSelectedPermissions();
        
        // Validiere nur das name-Feld, selectedPermissions wird separat behandelt
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(config('permission.table_names.roles'), 'name')->ignore($this->editingRoleId)],
        ]);
        
        // Validiere Permissions manuell
        $permissions = [];
        if (! empty($this->selectedPermissions) && is_array($this->selectedPermissions)) {
            $validPermissions = Permission::query()
                ->whereIn('name', $this->selectedPermissions)
                ->pluck('name')
                ->toArray();
            
            $invalidPermissions = array_diff($this->selectedPermissions, $validPermissions);
            
            if (! empty($invalidPermissions)) {
                $this->addError('selectedPermissions', __('Die folgenden Berechtigungen existieren nicht: :permissions', ['permissions' => implode(', ', $invalidPermissions)]));
                
                return;
            }
            
            $permissions = $this->selectedPermissions;
        }

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
        $this->reset('editingRoleId', 'name', 'isProtectedRole');
        $this->selectedPermissions = [];
    }

    // Permission methods
    public function openCreatePermissionModal(): void
    {
        $this->resetPermissionForm();
        $this->showPermissionFormModal = true;
    }

    public function editPermission(int $permissionId): void
    {
        $permission = Permission::findOrFail($permissionId);

        $this->editingPermissionId = $permission->id;
        $this->permissionName = $permission->name;
        $this->showPermissionFormModal = true;
    }

    public function savePermission(): void
    {
        $validated = $this->validate([
            'permissionName' => ['required', 'string', 'max:255', Rule::unique(config('permission.table_names.permissions'), 'name')->ignore($this->editingPermissionId)],
        ]);

        if ($this->editingPermissionId) {
            $permission = Permission::findOrFail($this->editingPermissionId);
            $permission->update(['name' => $validated['permissionName']]);
        } else {
            Permission::create([
                'name' => $validated['permissionName'],
                'guard_name' => 'web',
            ]);
            $this->resetPage();
        }

        $this->showPermissionFormModal = false;
        $this->resetPermissionForm();

        $this->dispatch('notify', title: __('Berechtigung gespeichert'));
    }

    public function confirmPermissionDelete(int $permissionId): void
    {
        $permission = Permission::findOrFail($permissionId);

        // Check if permission is used by any role
        $rolesUsingPermission = $permission->roles()->count();
        if ($rolesUsingPermission > 0) {
            $this->addError('deletePermission', __('Diese Berechtigung wird von :count Rolle(n) verwendet und kann nicht gelöscht werden.', ['count' => $rolesUsingPermission]));

            return;
        }

        $this->permissionIdBeingDeleted = $permission->id;
        $this->permissionNameBeingDeleted = $permission->name;
        $this->showPermissionDeleteModal = true;
    }

    public function deletePermission(): void
    {
        if ($this->permissionIdBeingDeleted) {
            $permission = Permission::findOrFail($this->permissionIdBeingDeleted);
            $permission->delete();
            $this->resetPage();
        }

        $this->showPermissionDeleteModal = false;
        $this->reset('permissionIdBeingDeleted', 'permissionNameBeingDeleted');

        $this->dispatch('notify', title: __('Berechtigung gelöscht'));
    }

    public function updatedShowPermissionFormModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->resetPermissionForm();
        }
    }

    public function updatedShowPermissionDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('permissionIdBeingDeleted', 'permissionNameBeingDeleted');
        }
    }

    protected function resetPermissionForm(): void
    {
        $this->reset('editingPermissionId', 'permissionName');
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Rollen & Berechtigungen') }}</flux:heading>
        <flux:subheading>{{ __('Verwalte Rollen und Berechtigungen für deine Anwendung') }}</flux:subheading>
    </div>

    <flux:tab.group>
        <flux:tabs variant="segmented" wire:model="activeTab">
            <flux:tab name="roles" icon="key">
                {{ __('Rollen') }}
            </flux:tab>
            <flux:tab name="permissions" icon="lock-closed">
                {{ __('Berechtigungen') }}
            </flux:tab>
        </flux:tabs>

        <flux:tab.panel name="roles">
            <div class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Rollenverwaltung') }}</flux:heading>
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
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="permissions">
            <div class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Berechtigungsverwaltung') }}</flux:heading>
                        <flux:subheading>{{ __('Erstelle und verwalte Berechtigungen') }}</flux:subheading>
                    </div>

                    <flux:button icon="plus" variant="primary" wire:click="openCreatePermissionModal">
                        {{ __('Berechtigung anlegen') }}
                    </flux:button>
                </div>

                @error('deletePermission')
                    <flux:callout variant="danger" icon="exclamation-triangle">
                        {{ $message }}
                    </flux:callout>
                @enderror

                <flux:input
                    wire:model.live.debounce.300ms="permissionSearch"
                    icon="magnifying-glass"
                    :placeholder="__('Berechtigung suchen...')"
                />

                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Name') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Verwendet von Rollen') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Aktionen') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse ($permissions as $permission)
                                <tr wire:key="permission-{{ $permission->id }}">
                                    <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $permission->name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                        @php
                                            $rolesCount = $permission->roles()->count();
                                        @endphp
                                        @if ($rolesCount > 0)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($permission->roles as $role)
                                                    <flux:badge size="sm" variant="subtle">{{ $role->name }}</flux:badge>
                                                @endforeach
                                            </div>
                                        @else
                                            <flux:text class="text-xs text-zinc-500">{{ __('Keine Rollen') }}</flux:text>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <flux:button size="xs" variant="outline" wire:click="editPermission({{ $permission->id }})">
                                                {{ __('Bearbeiten') }}
                                            </flux:button>
                                            <flux:button size="xs" variant="danger" wire:click="confirmPermissionDelete({{ $permission->id }})">
                                                {{ __('Löschen') }}
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Keine Berechtigungen vorhanden.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $permissions->links() }}
                </div>
            </div>
        </flux:tab.panel>
    </flux:tab.group>

    {{-- Role Modals --}}
    <flux:modal wire:model="showRoleFormModal" class="space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $editingRoleId ? __('Rolle bearbeiten') : __('Rolle anlegen') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Lege fest, welche Berechtigungen dieser Rolle zugewiesen werden.') }}
            </flux:subheading>
        </div>

        <form wire:submit.prevent="saveRole" class="space-y-4">
            @if ($errors->any())
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </flux:callout>
            @endif

            <flux:input
                wire:model="name"
                :label="__('Name')"
                type="text"
                required
                :disabled="$isProtectedRole"
            />
            @error('name')
                <flux:error>{{ $message }}</flux:error>
            @enderror

            <flux:pillbox
                wire:model="selectedPermissions"
                multiple
                :label="__('Berechtigungen')"
                searchable
                :placeholder="__('Berechtigungen auswählen...')"
                :search:placeholder="__('Berechtigungen suchen...')"
                :disabled="$isProtectedRole"
            >
                @foreach ($allPermissions as $permission)
                    <flux:pillbox.option value="{{ $permission->name }}">
                        {{ $permission->name }}
                    </flux:pillbox.option>
                @endforeach
            </flux:pillbox>
            @error('selectedPermissions')
                <flux:error>{{ $message }}</flux:error>
            @enderror
            @error('selectedPermissions.*')
                <flux:error>{{ $message }}</flux:error>
            @enderror

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

    {{-- Permission Modals --}}
    <flux:modal wire:model="showPermissionFormModal" class="space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $editingPermissionId ? __('Berechtigung bearbeiten') : __('Berechtigung anlegen') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Erstelle eine neue Berechtigung, die Rollen zugewiesen werden kann.') }}
            </flux:subheading>
        </div>

        <form wire:submit="savePermission" class="space-y-4">
            <flux:input
                wire:model="permissionName"
                :label="__('Name')"
                type="text"
                required
                placeholder="z.B. leagues.create, matches.edit"
            />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showPermissionFormModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ $editingPermissionId ? __('Aktualisieren') : __('Anlegen') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Permission Delete Modal --}}
    <flux:modal wire:model="showPermissionDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Berechtigung löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll die Berechtigung ":name" dauerhaft gelöscht werden?', ['name' => $permissionNameBeingDeleted]) }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showPermissionDeleteModal', false)">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button variant="danger" wire:click="deletePermission">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>
</section>

