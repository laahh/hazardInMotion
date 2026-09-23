<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PncMonitoringInventoryToolAsset extends Model
{
    /**
     * @var list<string>
     */
    public const STATUSES = [
        'Available',
        'Checked-out',
        'In Repair',
        'Quarantine',
        'Scrapped',
    ];

    /**
     * @var list<string>
     */
    public const CONDITIONS = [
        'Good',
        'Fair',
        'Poor',
        'Damaged',
    ];

    protected $table = 'pnc_monitoring_inventory_tool_assets';

    protected $primaryKey = 'asset_id';

    protected $fillable = [
        'tool_master_id',
        'inventory_id',
        'brand',
        'model',
        'serial_number',
        'year_made',
        'power_source',
        'status_availability',
        'location_detail',
        'condition',
        'owner_type',
        'owner_company_id',
        'assigned_to_user_id',
        'purchase_date',
        'purchase_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year_made' => 'integer',
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
        ];
    }

    public function toolMaster(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryToolMaster::class, 'tool_master_id', 'tool_master_id');
    }

    public function ownerCompany(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryCompany::class, 'owner_company_id', 'company_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function inspectionRecords(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolInspectionRecord::class, 'asset_id', 'asset_id');
    }

    public function calibrationRecords(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolCalibrationRecord::class, 'asset_id', 'asset_id');
    }

    public function checkoutRecords(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolCheckoutRecord::class, 'asset_id', 'asset_id');
    }
}
