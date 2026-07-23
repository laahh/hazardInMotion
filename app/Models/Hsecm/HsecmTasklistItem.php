<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HsecmTasklistItem extends Model
{
    protected $table = 'hsecm_tasklist_items';

    protected $fillable = [
        'tasklist_id',
        'program_key',
        'title',
        'business_key',
        'action_hint',
        'value_label',
        'payload',
        'status',
        'remediation_notes',
        'submitted_by_name',
        'submitted_at',
        'reviewed_by',
        'reviewed_by_name',
        'reviewed_at',
        'rejection_reason',
        'submission_batch',
    ];

    protected $casts = [
        'payload' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'submission_batch' => 'integer',
        'reviewed_by' => 'integer',
    ];

    public function tasklist(): BelongsTo
    {
        return $this->belongsTo(HsecmTasklist::class, 'tasklist_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(HsecmTasklistEvidence::class, 'tasklist_item_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function canSubmit(): bool
    {
        return in_array($this->status, ['open', 'rejected'], true);
    }
}
