<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Notification;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alert extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_alerts';

    protected $fillable = ['alertable_type', 'alertable_id', 'alert_type', 'due_date', 'threshold_days', 'status', 'sent_at', 'created_at'];

    protected $casts = [
        'due_date' => 'date',
        'threshold_days' => 'integer',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }
}
