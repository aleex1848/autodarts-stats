<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class SchedulerLog extends Model
{
    /** @use HasFactory<\Database\Factories\SchedulerLogFactory> */
    use HasFactory, MassPrunable;

    protected $fillable = [
        'scheduler_name',
        'status',
        'message',
        'affected_records',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
            'affected_records' => 'integer',
        ];
    }

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        $retentionDays = (int) Setting::get('scheduler.log_retention_days', 30);

        return static::where('executed_at', '<=', now()->subDays($retentionDays));
    }
}
