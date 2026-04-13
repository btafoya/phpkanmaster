<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $external_id
 * @property string $task_id
 * @property string $source
 * @property int|null $project_id
 * @property \DateTimeInterface|null $last_synced_at
 * @property \DateTimeInterface $created_at
 * @property \DateTimeInterface $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<IssueMapping> where($column, $operator = null, $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder<IssueMapping> whereIn($column, array $values)
 * @method static IssueMapping|null first($columns = ['*'])
 * @method static IssueMapping create(array $attributes)
 * @method static \Illuminate\Database\Eloquent\Builder<IssueMapping> orderBy($column, $direction = 'asc')
 */
class IssueMapping extends Model
{
    protected $table = 'issue_mappings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'external_id',
        'task_id',
        'source',
        'project_id',
        'last_synced_at',
    ];

    protected $casts = [
        'project_id' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}