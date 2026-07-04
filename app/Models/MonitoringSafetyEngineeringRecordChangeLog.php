<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringSafetyEngineeringRecordChangeLog extends Model
{
    protected $table = 'monitoring_safety_engineering_record_change_logs';

    protected $fillable = [
        'record_id',
        'change_batch',
        'action',
        'field_name',
        'field_label',
        'old_value',
        'new_value',
        'changed_at',
        'changed_by',
        'period_year',
        'review_week',
    ];

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'changed_at' => 'datetime',
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
