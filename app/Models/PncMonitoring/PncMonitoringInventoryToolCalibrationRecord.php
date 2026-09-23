<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PncMonitoringInventoryToolCalibrationRecord extends Model
{
    public $timestamps = false;

    protected $table = 'pnc_monitoring_inventory_tool_calibration_records';

    protected $primaryKey = 'record_id';

    protected $fillable = [
        'asset_id',
        'calibration_date',
        'certificate_no',
        'standard_method',
        'result',
        'next_due_date',
        'certificate_file_url',
    ];

    protected function casts(): array
    {
        return [
            'calibration_date' => 'date',
            'next_due_date' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryToolAsset::class, 'asset_id', 'asset_id');
    }
}
