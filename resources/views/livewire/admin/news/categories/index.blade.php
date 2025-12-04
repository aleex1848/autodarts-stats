<?php

use App\Models\NewsCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingCategoryId = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public ?string $color = null;
    public bool $showCategoryFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $categoryIdBeingDeleted = null;
    public ?string $categoryNameBeingDeleted = null;

    public function with(): array
    {
        return [
            'categories' => NewsCategory::query()->withCount('news')->orderBy('name')->get(),
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(NewsCategory::class, 'slug')->ignore($this->editingCategoryId),
            ],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'in:red,blue,green,yellow,purple'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetCategoryForm();
        $this->showCategoryFormModal = true;
    }

    public function editCategory(int $categoryId): void
    {
        $category = NewsCategory::findOrFail($categoryId);

        $this->editingCategoryId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = $category->description ?? '';
        $this->color = $category->color;
        $this->showCategoryFormModal = true;
    }

    public function saveCategory(): void
    {
        $validated = $this->validate();

        // Auto-generate slug if empty
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if ($this->editingCategoryId) {
            $category = NewsCategory::findOrFail($this->editingCategoryId);
            $category->update($validated);
        } else {
            NewsCategory::create($validated);
        }

        $this->showCategoryFormModal = false;
        $this->resetCategoryForm();

        $this->dispatch('notify', title: __('Kategorie gespeichert'));
    }

    public function confirmDelete(int $categoryId): void
    {
        $category = NewsCategory::withCount('news')->findOrFail($categoryId);

        if ($category->news_count > 0) {
            $this->addError('delete', __('Diese Kategorie kann nicht gelöscht werden, da sie noch News enthält.'));

            return;
        }

        $this->categoryIdBeingDeleted = $category->id;
        $this->categoryNameBeingDeleted = $category->name;
        $this->showDeleteModal = true;
    }

    public function deleteCategory(): void
    {
        if ($this->categoryIdBeingDeleted) {
            $category = NewsCategory::findOrFail($this->categoryIdBeingDeleted);
            $category->delete();
        }

        $this->showDeleteModal = false;
        $this->reset('categoryIdBeingDeleted', 'categoryNameBeingDeleted');
    }

    public function updatedShowCategoryFormModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->resetCategoryForm();
        }
    }

    public function updatedShowDeleteModal(bool $isOpen): void
    {
        if (! $isOpen) {
            $this->reset('categoryIdBeingDeleted', 'categoryNameBeingDeleted');
        }
    }

    public function updatedName(string $value): void
    {
        if (! $this->editingCategoryId && empty($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    protected function resetCategoryForm(): void
    {
        $this->reset('editingCategoryId', 'name', 'slug', 'description', 'color');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('News-Kategorien') }}</flux:heading>
            <flux:subheading>{{ __('Verwalte News-Kategorien') }}</flux:subheading>
        </div>

        <flux:button icon="plus" variant="primary" wire:click="openCreateModal">
            {{ __('Kategorie anlegen') }}
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
                        {{ __('Slug') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Farbe') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('News') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        {{ __('Aktionen') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($categories as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $category->name }}
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            <code class="rounded bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800">{{ $category->slug }}</code>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            @if ($category->color)
                                <flux:badge size="sm" :variant="'subtle'">
                                    {{ $category->color }}
                                </flux:badge>
                            @else
                                <span class="text-zinc-400 dark:text-zinc-500">{{ __('Keine') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            <flux:badge size="sm" variant="subtle">{{ $category->news_count }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" variant="outline" wire:click="editCategory({{ $category->id }})">
                                    {{ __('Bearbeiten') }}
                                </flux:button>

                                <flux:button
                                    size="xs"
                                    variant="danger"
                                    wire:click="confirmDelete({{ $category->id }})"
                                    :disabled="$category->news_count > 0"
                                >
                                    {{ __('Löschen') }}
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Keine Kategorien vorhanden.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal wire:model="showCategoryFormModal" class="space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $editingCategoryId ? __('Kategorie bearbeiten') : __('Kategorie anlegen') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Pflege Name, Slug, Beschreibung und Farbe der Kategorie') }}
            </flux:subheading>
        </div>

        <form wire:submit="saveCategory" class="space-y-4">
            <flux:input wire:model="name" :label="__('Name')" type="text" required />
            <flux:input wire:model="slug" :label="__('Slug')" type="text" />
            <flux:description>{{ __('Wird automatisch generiert, wenn leer gelassen') }}</flux:description>
            <flux:textarea wire:model="description" :label="__('Beschreibung')" rows="3" />
            <flux:select wire:model="color" :label="__('Farbe (optional)')" :placeholder="__('Keine Farbe')">
                <flux:select.option value="">{{ __('Keine Farbe') }}</flux:select.option>
                <flux:select.option value="red">{{ __('Rot') }}</flux:select.option>
                <flux:select.option value="blue">{{ __('Blau') }}</flux:select.option>
                <flux:select.option value="green">{{ __('Grün') }}</flux:select.option>
                <flux:select.option value="yellow">{{ __('Gelb') }}</flux:select.option>
                <flux:select.option value="purple">{{ __('Lila') }}</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCategoryFormModal', false)">
                    {{ __('Abbrechen') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ $editingCategoryId ? __('Aktualisieren') : __('Anlegen') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Kategorie löschen') }}</flux:heading>
            <flux:subheading>
                {{ __('Soll die Kategorie ":name" wirklich entfernt werden? Diese Aktion kann nicht rückgängig gemacht werden.', ['name' => $categoryNameBeingDeleted]) }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button variant="danger" wire:click="deleteCategory">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>
</section>

