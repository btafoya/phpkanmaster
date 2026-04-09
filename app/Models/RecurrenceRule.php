<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurrenceRule extends Model
{
    protected $table = 'recurrence_rules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'task_id', 'rrule', 'next_occurrence_at', 'end_date', 'active',
    ];

    protected $casts = [
        'active'             => 'boolean',
        'next_occurrence_at' => 'datetime',
        'end_date'           => 'date',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
