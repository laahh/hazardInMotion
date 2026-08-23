<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Inspection;

use App\Models\EmergencyResponse\MasterData\ChecklistTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InspectionSchedule extends Model
{
    use HasUuids;

    protected $table = 'er_inspection_schedules';

    protected $fillable = [
        'target_type', 'target_id', 'checklist_template_id', 'frequency_days',
        'next_due_date', 'assigned_inspector_id', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'frequency_days' => 'integer',
        'next_due_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function checklistTemplate()
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }

    public function assignedInspector()
    {
        return $this->belongsTo(User::class, 'assigned_inspector_id');
    }
}
