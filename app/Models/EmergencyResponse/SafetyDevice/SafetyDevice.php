<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\SafetyDevice;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\EmergencyResponse\MasterData\Area;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\Location;
use App\Models\EmergencyResponse\MasterData\SafetyDeviceType;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\Shared\Concerns\TracksEquipmentStatusHistory;
use App\Models\EmergencyResponse\Shared\EquipmentDocument;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SafetyDevice extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail, TracksEquipmentStatusHistory;

    protected $table = 'er_safety_devices';

    public const CONDITIONS = [
        'baik' => 'Baik',
        'perlu_perbaikan' => 'Perlu Perbaikan',
        'rusak' => 'Rusak',
        'maintenance' => 'Dalam Maintenance',
        'tidak_aktif' => 'Tidak Aktif',
    ];

    public const OPERATIONAL_STATUSES = [
        'available' => 'Available',
        'in_use' => 'In Use',
        'maintenance' => 'Maintenance',
        'out_of_service' => 'Out of Service',
    ];

    protected $fillable = [
        'code', 'name', 'safety_device_type_id', 'brand', 'model', 'serial_number',
        'site_id', 'location_id', 'area_id', 'position_detail', 'latitude', 'longitude',
        'department_id', 'installed_at', 'condition', 'operational_status',
        'last_inspection_at', 'next_inspection_at', 'last_calibration_at', 'next_calibration_at',
        'certificate_expires_at', 'photo_path', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'last_inspection_at' => 'date',
        'next_inspection_at' => 'date',
        'last_calibration_at' => 'date',
        'next_calibration_at' => 'date',
        'certificate_expires_at' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected array $auditExcept = ['photo_path'];

    public function type()
    {
        return $this->belongsTo(SafetyDeviceType::class, 'safety_device_type_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function documents()
    {
        return $this->morphMany(EquipmentDocument::class, 'documentable');
    }

    public function inspections()
    {
        return $this->morphMany(\App\Models\EmergencyResponse\Inspection\Inspection::class, 'target')->latest('inspected_at');
    }

    public function workOrders()
    {
        return $this->morphMany(\App\Models\EmergencyResponse\Maintenance\WorkOrder::class, 'equipmentable')->latest('requested_at');
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }

    public function operationalStatusLabel(): string
    {
        return self::OPERATIONAL_STATUSES[$this->operational_status] ?? $this->operational_status;
    }
}
