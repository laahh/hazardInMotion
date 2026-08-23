<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Inspection;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InspectionFinding extends Model
{
    use HasUuids, LogsAuditTrail;

    protected $table = 'er_inspection_findings';

    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
    ];

    protected $fillable = [
        'inspection_id', 'inspection_result_id', 'description', 'pic_id', 'target_date',
        'status', 'work_order_id', 'resolved_at', 'resolved_notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'target_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function inspection()
    {
        return $this->belongsTo(Inspection::class);
    }

    public function result()
    {
        return $this->belongsTo(InspectionResult::class, 'inspection_result_id');
    }

    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
