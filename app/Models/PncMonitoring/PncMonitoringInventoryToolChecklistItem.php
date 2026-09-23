<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PncMonitoringInventoryToolChecklistItem extends Model
{
    public $timestamps = false;

    protected $table = 'pnc_monitoring_inventory_tool_checklist_items';

    protected $fillable = [
        'tool_master_id',
        'sequence',
        'komponen_diperiksa',
        'kriteria_pemeriksaan',
    ];

    public function toolMaster(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryToolMaster::class, 'tool_master_id', 'tool_master_id');
    }

    public function inspectionRecordDetails(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryInspectionRecordDetail::class, 'checklist_item_id');
    }
}
