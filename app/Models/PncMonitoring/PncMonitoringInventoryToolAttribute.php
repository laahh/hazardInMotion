<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PncMonitoringInventoryToolAttribute extends Model
{
    public $timestamps = false;

    protected $table = 'pnc_monitoring_inventory_tool_attributes';

    protected $fillable = [
        'tool_master_id',
        'attribute_name',
        'attribute_value',
    ];

    public function toolMaster(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryToolMaster::class, 'tool_master_id', 'tool_master_id');
    }
}
