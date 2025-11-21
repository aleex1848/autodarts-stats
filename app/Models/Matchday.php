<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matchday extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'matchday_number',
        'is_return_round',
        'deadline_at',
        'is_playoff',
    ];

    protected function casts(): array
    {
        return [
            'matchday_number' => 'integer',
            'is_return_round' => 'boolean',
            'deadline_at' => 'datetime',
            'is_playoff' => 'boolean',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(MatchdayFixture::class);
    }
}
