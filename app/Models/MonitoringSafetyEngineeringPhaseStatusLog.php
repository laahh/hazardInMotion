<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MonitoringSafetyEngineeringPhase;
use App\Enums\MonitoringSafetyEngineeringStatusCompliance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringSafetyEngineeringPhaseStatusLog extends Model
{
    protected $table = 'monitoring_safety_engineering_phase_status_logs';

    protected $fillable = [
        'record_id',
        'phase',
        'old_status',
        'new_status',
        'due_date',
        'changed_at',
        'changed_by',
        'compliance',
        'period_year',
        'review_week',
    ];

    protected function casts(): array
    {
        return [
            'phase' => MonitoringSafetyEngineeringPhase::class,
            'due_date' => 'date',
            'changed_at' => 'datetime',
            'compliance' => MonitoringSafetyEngineeringStatusCompliance::class,
            'period_year' => 'integer',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(MonitoringSafetyEngineeringRecord::class, 'record_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
