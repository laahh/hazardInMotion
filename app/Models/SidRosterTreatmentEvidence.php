<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidRosterTreatmentEvidence extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'sid_roster_treatment_evidence';

    public $timestamps = false;

    protected $fillable = [
        'master_id',
        'nik',
        'sid',
        'evidence_file_path',
        'tanggal_treatment',
        'periode_cuti',
        'catatan',
        'submitted_by',
        'submitted_at',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'tanggal_treatment' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function master(): BelongsTo
    {
        return $this->belongsTo(SidRosterBannedMaster::class, 'master_id');
    }

    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }
}
