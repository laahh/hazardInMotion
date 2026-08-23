<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Notification;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_notification_logs';

    protected $fillable = ['notification_id', 'channel', 'recipient', 'status', 'error_message', 'sent_at', 'created_at'];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
