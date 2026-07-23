<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HsecmTasklist extends Model
{
    protected $table = 'hsecm_tasklists';

    protected $fillable = [
        'token',
        'site',
        'perusahaan',
        'batch_slot',
        'status',
        'escalate_count',
        'last_escalated_at',
        'next_escalate_at',
        'closed_at',
    ];

    protected $casts = [
        'batch_slot' => 'datetime',
        'last_escalated_at' => 'datetime',
        'next_escalate_at' => 'datetime',
        'closed_at' => 'datetime',
        'escalate_count' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(HsecmTasklistItem::class, 'tasklist_id');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
