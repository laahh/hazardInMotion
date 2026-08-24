<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Equipment;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\EmergencyResponse\MasterData\Area;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\EmergencyUnit;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;
use App\Models\EmergencyResponse\MasterData\Location;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\Shared\Concerns\TracksEquipmentStatusHistory;
use App\Models\EmergencyResponse\Shared\EquipmentDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmergencyEquipment extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail, TracksEquipmentStatusHistory;

    protected $table = 'er_emergency_equipment';

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
        'code', 'name', 'equipment_category_id', 'type_model', 'brand', 'serial_number',
        'site_id', 'location_id', 'location_name', 'area_id', 'area_name', 'position_detail', 'latitude', 'longitude',
        'department_id', 'emergency_unit_id', 'purchased_at', 'commissioned_at',
        'condition', 'operational_status', 'last_inspection_at', 'next_inspection_at',
        'last_calibration_at', 'expires_at', 'certificate_number', 'certificate_expires_at',
        'photo_path', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'commissioned_at' => 'date',
        'last_inspection_at' => 'date',
        'next_inspection_at' => 'date',
        'last_calibration_at' => 'date',
        'expires_at' => 'date',
        'certificate_expires_at' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected array $auditExcept = ['photo_path'];

    public function category()
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
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

    public function emergencyUnit()
    {
        return $this->belongsTo(EmergencyUnit::class);
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locationLabel(): ?string
    {
        $name = trim((string) ($this->location_name ?? ''));

        return $name !== '' ? $name : $this->location?->name;
    }

    public function areaLabel(): ?string
    {
        $name = trim((string) ($this->area_name ?? ''));

        return $name !== '' ? $name : $this->area?->name;
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
