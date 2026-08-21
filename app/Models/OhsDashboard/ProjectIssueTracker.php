<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectIssueTracker extends Model
{
    protected $table = 'ohs_project_issue_trackers';

    protected $primaryKey = 'tracker_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tracker_id',
        'timestamp',
        'tracker_type',
        'project_issue_name',
        'department',
        'site',
        'project_leader_emp_id',
        'project_leader_name',
        'project_leader_team',
        'project_leader_position',
        'project_leader_site_dedicated',
        'description_project',
        'background_project',
        'impact_project',
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

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'project_leader_emp_id', 'emp_id');
    }

    public function subTasks(): HasMany
    {
        return $this->hasMany(ProjectIssueSubTask::class, 'tracker_id', 'tracker_id');
    }

    public function updateLogs(): HasMany
    {
        return $this->hasMany(ProjectIssueUpdateLog::class, 'tracker_id', 'tracker_id');
    }
}
