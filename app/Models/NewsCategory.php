<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'category_id');
    }

    /**
     * Get the color class for badge styling.
     */
    public function getColorClass(): string
    {
        return match ($this->color) {
            'red' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            default => 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
        };
    }
}

