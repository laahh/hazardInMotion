<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    protected $table = 'ohs_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'timestamp',
        'event_name',
        'description',
        'where',
        'readiness_update',
        'readiness_updated_at',
        'pic_emp_id',
        'pic_name',
        'pic_team',
        'pic_position',
        'pic_site_dedicated',
        'event_date',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'readiness_updated_at' => 'datetime',
        'event_date' => 'date',
    ];

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_emp_id', 'emp_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class, 'event_id', 'event_id');
    }

    public function minutes(): HasOne
    {
        return $this->hasOne(EventMinute::class, 'event_id', 'event_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(EventActionItem::class, 'event_id', 'event_id');
    }
}
