<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PncMonitoringInventoryToolInspectionRecord extends Model
{
    public $timestamps = false;

    protected $table = 'pnc_monitoring_inventory_tool_inspection_records';

    protected $primaryKey = 'record_id';

    protected $fillable = [
        'asset_id',
        'inspection_date',
        'inspector_user_id',
        'overall_result',
        'next_due_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'next_due_date' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryToolAsset::class, 'asset_id', 'asset_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryInspectionRecordDetail::class, 'record_id', 'record_id');
    }
}
