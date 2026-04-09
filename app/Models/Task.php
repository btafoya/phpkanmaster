<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property string|null $due_date
 * @property string $priority
 * @property string $task_column
 * @property int $position
 * @property string|null $category_id
 * @property string|null $parent_id
 * @property \DateTimeInterface|null $reminder_at
 * @property bool $reminder_sent
 * @property int $pushover_priority
 * @property int|null $pushover_retry
 * @property int|null $pushover_expire
 * @property bool $disable_notifications
 * @method static \Illuminate\Database\Eloquent\Builder<Task> where($column, $operator = null, $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder<Task> whereDate($column, $operator, $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder<Task> whereIn($column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder<Task> whereNull($column)
 * @method static \Illuminate\Database\Eloquent\Builder<Task> whereNotNull($column)
 * @method static \Illuminate\Database\Eloquent\Builder<Task> orderBy($column, $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<Task> find($id, $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<Task> first($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<Task> get($columns = ['*'])
 * @method static int count($columns = ['*'])
 * @method static Task firstOrCreate(array $attributes, array $values = [])
 * @method static Task create(array $attributes)
 * @method static void truncate()
 * @method static \Illuminate\Database\Eloquent\Collection<Task> all($columns = ['*'])
 */
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
