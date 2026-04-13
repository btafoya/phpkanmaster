<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $source
 * @property int $external_status
 * @property string $kanban_column
 * @property \DateTimeInterface $created_at
 * @method static \Illuminate\Database\Eloquent\Builder<WebhookStatusMapping> where($column, $operator = null, $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder<WebhookStatusMapping> whereIn($column, array $values)
 * @method static WebhookStatusMapping|null first($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<WebhookStatusMapping> get($columns = ['*'])
 */
class WebhookStatusMapping extends Model
{
    protected $table = 'webhook_status_mappings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'source',
        'external_status',
        'kanban_column',
    ];

    protected $casts = [
        'external_status' => 'integer',
    ];
}