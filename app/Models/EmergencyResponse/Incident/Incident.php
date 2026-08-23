<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Incident;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\EmergencyResponse\MasterData\IncidentType;
use App\Models\EmergencyResponse\MasterData\PriorityLevel;
use App\Models\EmergencyResponse\MasterData\SeverityLevel;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_incidents';

    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'incident_number', 'occurred_at', 'reported_at', 'incident_type_id', 'severity_level_id',
        'priority_level_id', 'site_id', 'location_detail', 'latitude', 'longitude', 'description',
        'victim_count', 'potential_hazards', 'assistance_needed', 'reported_by', 'reporter_name',
        'reporter_phone', 'reporter_department', 'status', 'confirmed_at', 'dispatched_at',
        'arrived_at', 'handling_started_at', 'contained_at', 'handling_completed_at',
        'is_possible_duplicate', 'possible_duplicate_of', 'is_escalated', 'root_cause',
        'corrective_action', 'closed_at', 'closed_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'reported_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'arrived_at' => 'datetime',
        'handling_started_at' => 'datetime',
        'contained_at' => 'datetime',
        'handling_completed_at' => 'datetime',
        'closed_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_possible_duplicate' => 'boolean',
        'is_escalated' => 'boolean',
        'victim_count' => 'integer',
    ];

    public function incidentType()
    {
        return $this->belongsTo(IncidentType::class);
    }

    public function severityLevel()
    {
        return $this->belongsTo(SeverityLevel::class);
    }

    public function priorityLevel()
    {
        return $this->belongsTo(PriorityLevel::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function possibleDuplicateOf()
    {
        return $this->belongsTo(self::class, 'possible_duplicate_of');
    }

    public function victims()
    {
        return $this->hasMany(IncidentVictim::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(IncidentStatusHistory::class)->orderByDesc('changed_at');
    }

    public function assignments()
    {
        return $this->hasMany(IncidentAssignment::class)->orderByDesc('assigned_at');
    }

    public function responseUnits()
    {
        return $this->hasMany(ResponseUnit::class);
    }

    public function equipmentUsages()
    {
        return $this->hasMany(IncidentEquipmentUsage::class);
    }

    public function attachments()
    {
        return $this->hasMany(IncidentAttachment::class);
    }

    public function timeline()
    {
        return $this->hasMany(IncidentTimeline::class)->orderByDesc('created_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
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

        $this->addTimelineEntry('status_change', "Status berubah dari \"{$this->statusLabel()}\" ke \"".(self::STATUSES[$toStatus] ?? $toStatus).'"'.($notes ? " — {$notes}" : ''), $changedBy);
    }

    public function addTimelineEntry(string $eventType, string $description, ?int $createdBy, bool $isInternal = false): IncidentTimeline
    {
        return $this->timeline()->create([
            'event_type' => $eventType,
            'description' => $description,
            'is_internal' => $isInternal,
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);
    }

    public function responseTimeMinutes(): ?int
    {
        return $this->arrived_at ? $this->reported_at->diffInMinutes($this->arrived_at) : null;
    }

    public function handlingTimeMinutes(): ?int
    {
        return $this->handling_started_at && $this->handling_completed_at
            ? $this->handling_started_at->diffInMinutes($this->handling_completed_at)
            : null;
    }
}
