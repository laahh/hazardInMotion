<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Inspection;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\EmergencyResponse\MasterData\ChecklistTemplate;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inspection extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_inspections';

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'follow_up_required' => 'Follow-up Required',
        'completed' => 'Completed',
        'overdue' => 'Overdue',
    ];

    protected $fillable = [
        'inspection_number', 'inspection_schedule_id', 'target_type', 'target_id',
        'checklist_template_id', 'site_id', 'inspector_id',
        'inspector_name', 'inspector_nik', 'inspector_sid', 'inspector_phone', 'status', 'condition_result',
        'notes', 'signature_path', 'latitude', 'longitude', 'inspected_at', 'submitted_at',
        'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'approval_notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'inspected_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected array $auditExcept = ['signature_path'];

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function checklistTemplate()
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function results()
    {
        return $this->hasMany(InspectionResult::class)->orderBy('sort_order');
    }

    public function findings()
    {
        return $this->hasMany(InspectionFinding::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Inspeksi dari form publik tidak punya akun user, jadi namanya diambil
     * dari kolom `inspector_name` yang diisi manual di form.
     */
    public function inspectorLabel(): ?string
    {
        $name = trim((string) ($this->inspector_name ?? ''));

        return $name !== '' ? $name : $this->inspector?->name;
    }

    /**
     * NIK dan/atau SID inspector, dirangkai untuk ditampilkan di satu baris.
     */
    public function inspectorIdentity(): ?string
    {
        $parts = array_filter([
            trim((string) ($this->inspector_nik ?? '')),
            trim((string) ($this->inspector_sid ?? '')),
        ], fn (string $part): bool => $part !== '');

        return $parts === [] ? null : implode(' / ', $parts);
    }

    public function isPublicSubmission(): bool
    {
        return $this->inspector_id === null && trim((string) ($this->inspector_name ?? '')) !== '';
    }
}
