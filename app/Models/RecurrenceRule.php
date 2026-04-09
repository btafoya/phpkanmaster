<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $task_id
 * @property string $rrule
 * @property \DateTimeInterface|null $next_occurrence_at
 * @property \DateTimeInterface|null $end_date
 * @property bool $active
 * @method static \Illuminate\Database\Eloquent\Builder<RecurrenceRule> where($column, $operator = null, $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder<RecurrenceRule> whereIn($column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder<RecurrenceRule> whereNull($column)
 * @method static \Illuminate\Database\Eloquent\Builder<RecurrenceRule> whereNotNull($column)
 * @method static \Illuminate\Database\Eloquent\Builder<RecurrenceRule> orderBy($column, $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<RecurrenceRule> find($id, $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<RecurrenceRule> first($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<RecurrenceRule> get($columns = ['*'])
 * @method static int count($columns = ['*'])
 * @method static RecurrenceRule firstOrCreate(array $attributes, array $values = [])
 * @method static RecurrenceRule create(array $attributes)
 */
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
