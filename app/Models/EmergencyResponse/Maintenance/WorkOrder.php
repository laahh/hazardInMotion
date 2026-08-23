<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Maintenance;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\EmergencyResponse\Incident\Incident;
use App\Models\EmergencyResponse\Inspection\InspectionFinding;
use App\Models\EmergencyResponse\MasterData\PriorityLevel;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\MasterData\Vendor;
use App\Models\EmergencyResponse\Shared\EquipmentDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_work_orders';

    public const STATUSES = [
        'requested' => 'Requested',
        'approved' => 'Approved',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'verified' => 'Verified',
        'closed' => 'Closed',
    ];

    public const WORK_TYPES = [
        'corrective' => 'Corrective Maintenance',
        'preventive' => 'Preventive Maintenance',
        'inspection_followup' => 'Inspection Follow-up',
        'calibration' => 'Calibration',
        'vehicle_service' => 'Vehicle Service',
        'certification_renewal' => 'Certification Renewal',
        'emergency_repair' => 'Emergency Repair',
    ];

    public const SOURCES = ['manual' => 'Manual', 'inspection' => 'Inspeksi', 'incident' => 'Insiden', 'alert' => 'Alert'];

    protected $fillable = [
        'work_order_number', 'equipmentable_type', 'equipmentable_id', 'site_id', 'work_type', 'source',
        'source_inspection_finding_id', 'source_incident_id', 'description', 'priority_level_id',
        'assigned_technician_id', 'vendor_id', 'requested_at', 'target_start_at', 'target_end_at',
        'actual_start_at', 'actual_end_at', 'estimated_cost', 'actual_cost', 'status', 'approved_by',
        'approved_at', 'verified_by', 'verified_at', 'result_notes', 'technician_signature_path',
        'verifier_signature_path', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'target_start_at' => 'date',
        'target_end_at' => 'date',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
        'approved_at' => 'datetime',
        'verified_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    protected array $auditExcept = ['technician_signature_path', 'verifier_signature_path'];

    public function equipmentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function priorityLevel()
    {
        return $this->belongsTo(PriorityLevel::class);
    }

    public function assignedTechnician()
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function sourceInspectionFinding()
    {
        return $this->belongsTo(InspectionFinding::class, 'source_inspection_finding_id');
    }

    public function sourceIncident()
    {
        return $this->belongsTo(Incident::class, 'source_incident_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function statusHistories()
    {
        return $this->hasMany(WorkOrderStatusHistory::class)->orderByDesc('changed_at');
    }

    public function spareParts()
    {
        return $this->hasMany(WorkOrderSparePart::class);
    }

    public function documents()
    {
        return $this->morphMany(EquipmentDocument::class, 'documentable');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function workTypeLabel(): string
    {
        return self::WORK_TYPES[$this->work_type] ?? $this->work_type;
    }

    public function recordStatusChange(string $toStatus, ?string $notes, ?int $changedBy): void
    {
        $this->statusHistories()->create([
            'from_status' => $this->status,
            'to_status' => $toStatus,
            'notes' => $notes,
            'changed_by' => $changedBy,
            'changed_at' => now(),
        ]);
    }

    public function totalSparePartCost(): float
    {
        return $this->spareParts->sum(fn ($item) => $item->subtotal());
    }
}
