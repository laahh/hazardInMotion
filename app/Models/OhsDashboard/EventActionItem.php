<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventActionItem extends Model
{
    protected $table = 'ohs_event_action_items';

    protected $primaryKey = 'action_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'action_item_id',
        'timestamp',
        'event_id',
        'task',
        'pic_emp_id',
        'pic_name',
        'due_date',
        'status',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'due_date' => 'date',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }
}
