<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PncMonitoringInventoryInspectionRecordDetail extends Model
{
    public $timestamps = false;

    protected $table = 'pnc_monitoring_inventory_inspection_record_details';

    protected $fillable = [
        'record_id',
        'checklist_item_id',
        'result',
        'notes',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryToolInspectionRecord::class, 'record_id', 'record_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryToolChecklistItem::class, 'checklist_item_id');
    }
}
