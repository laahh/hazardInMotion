<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $table = 'ohs_leave_requests';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'timestamp',
        'emp_id',
        'emp_name',
        'team',
        'position',
        'site_dedicated',
        'leave_type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'note',
        'backup_emp_id',
        'backup_emp_name',
        'backup_team',
        'backup_position',
        'backup_site_dedicated',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }

    public function backupEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'backup_emp_id', 'emp_id');
    }
}
