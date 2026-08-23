<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Notification;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasUuids;

    protected $table = 'er_notification_preferences';

    protected $fillable = ['user_id', 'email_enabled', 'in_app_enabled'];

    protected $casts = [
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
