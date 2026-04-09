<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    protected $table = 'tasks';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'description', 'due_date', 'priority', 'task_column',
        'position', 'category_id', 'parent_id', 'reminder_at', 'reminder_sent',
        'pushover_priority', 'pushover_retry', 'pushover_expire',
        'disable_notifications',
    ];

    protected $casts = [
        'disable_notifications' => 'boolean',
        'reminder_sent'         => 'boolean',
        'reminder_at'           => 'datetime',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function recurrenceRule(): HasOne
    {
        return $this->hasOne(RecurrenceRule::class, 'task_id');
    }
}
