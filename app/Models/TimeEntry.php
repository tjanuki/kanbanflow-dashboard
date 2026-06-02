<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'type',
        'started_at',
        'ended_at',
        'seconds',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** Entries that have not been stopped yet. */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /** Entries started today. */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('started_at', today());
    }
}
