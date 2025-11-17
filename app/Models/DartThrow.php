<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\WebhookClient\Models\WebhookCall;

class DartThrow extends Model
{
    /** @use HasFactory<\Database\Factories\DartThrowFactory> */
    use HasFactory;

    protected $table = 'throws';

    protected $fillable = [
        'turn_id',
        'autodarts_throw_id',
        'webhook_call_id',
        'dart_number',
        'segment_number',
        'multiplier',
        'points',
        'segment_name',
        'segment_bed',
        'coords_x',
        'coords_y',
        'is_corrected',
        'corrected_at',
        'corrected_by_throw_id',
    ];

    protected function casts(): array
    {
        return [
            'is_corrected' => 'boolean',
            'corrected_at' => 'datetime',
            'coords_x' => 'decimal:8',
            'coords_y' => 'decimal:8',
        ];
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    public function webhookCall(): BelongsTo
    {
        return $this->belongsTo(WebhookCall::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(DartThrow::class, 'corrected_by_throw_id');
    }

    public function correctedThrow(): BelongsTo
    {
        return $this->belongsTo(DartThrow::class, 'id', 'corrected_by_throw_id');
    }

    public function scopeNotCorrected(Builder $query): void
    {
        $query->where('is_corrected', false);
    }

    public function scopeCorrected(Builder $query): void
    {
        $query->where('is_corrected', true);
    }
}
