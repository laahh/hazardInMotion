<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendance extends Model
{
    protected $table = 'ohs_event_attendances';

    protected $primaryKey = 'attendance_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'attendance_id',
        'timestamp',
        'event_id',
        'emp_id',
        'emp_name',
        'team',
        'position',
        'site_dedicated',
        'check_in_at',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'check_in_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }
}
