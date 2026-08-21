<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectIssueSubTask extends Model
{
    protected $table = 'ohs_project_issue_sub_tasks';

    protected $primaryKey = 'sub_task_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'sub_task_id',
        'tracker_id',
        'timestamp',
        'sub_task_name',
        'department',
        'site',
        'pic_emp_id',
        'pic_name',
        'pic_team',
        'pic_position',
        'pic_site_dedicated',
        'description_sub_task',
        'start_date',
        'due_date',
        'success_indicator',
        'current_percent_complete',
        'current_progress_report_weekly',
        'current_remarks',
        'status',
        'last_updated',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'start_date' => 'date',
        'due_date' => 'date',
        'current_percent_complete' => 'decimal:2',
        'last_updated' => 'datetime',
    ];

    public function tracker(): BelongsTo
    {
        return $this->belongsTo(ProjectIssueTracker::class, 'tracker_id', 'tracker_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_emp_id', 'emp_id');
    }

    public function updateLogs(): HasMany
    {
        return $this->hasMany(ProjectIssueSubTaskUpdateLog::class, 'sub_task_id', 'sub_task_id');
    }
}
