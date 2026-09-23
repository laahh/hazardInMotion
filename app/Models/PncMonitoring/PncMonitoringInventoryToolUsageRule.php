<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PncMonitoringInventoryToolUsageRule extends Model
{
    public $timestamps = false;

    protected $table = 'pnc_monitoring_inventory_tool_usage_rules';

    protected $fillable = [
        'tool_master_id',
        'rule_type',
        'sequence',
        'description',
    ];

    public function toolMaster(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryToolMaster::class, 'tool_master_id', 'tool_master_id');
    }
}
