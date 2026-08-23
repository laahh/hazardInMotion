<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Notification;

use App\Models\EmergencyResponse\MasterData\NotificationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_notifications';

    protected $fillable = [
        'user_id', 'notification_template_id', 'type', 'title', 'message', 'link_url',
        'is_read', 'read_at', 'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class);
    }
}
