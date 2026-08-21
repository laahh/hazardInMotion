<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectIssueSubTaskUpdateLog extends Model
{
    protected $table = 'ohs_project_issue_sub_task_update_logs';

    protected $primaryKey = 'update_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'update_id',
        'timestamp',
        'tracker_id',
        'sub_task_id',
        'percent_complete',
        'progress_report_weekly',
        'remarks',
        'status',
        'updated_by_emp_id',
        'updated_by_name',
        'updated_by_team',
        'updated_by_position',
        'updated_by_site_dedicated',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'percent_complete' => 'decimal:2',
    ];

    public function tracker(): BelongsTo
    {
        return $this->belongsTo(ProjectIssueTracker::class, 'tracker_id', 'tracker_id');
    }

    public function subTask(): BelongsTo
    {
        return $this->belongsTo(ProjectIssueSubTask::class, 'sub_task_id', 'sub_task_id');
    }
}
