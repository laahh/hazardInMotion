<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Maintenance;

use App\Models\EmergencyResponse\MasterData\MaintenanceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaintenanceSchedule extends Model
{
    use HasUuids;

    protected $table = 'er_maintenance_schedules';

    protected $fillable = [
        'target_type', 'target_id', 'maintenance_type_id', 'frequency_days', 'next_due_date',
        'assigned_technician_id', 'is_active', 'created_by', 'updated_by',
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

    public function maintenanceType()
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    public function assignedTechnician()
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }
}
