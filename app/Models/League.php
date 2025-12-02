<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'banner_path',
        'logo_path',
        'discord_invite_link',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function coAdmins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_co_admins')
            ->withTimestamps();
    }

    public function isAdmin(User $user): bool
    {
        return $this->created_by_user_id === $user->id
            || $this->coAdmins()->where('user_id', $user->id)->exists();
    }
}
