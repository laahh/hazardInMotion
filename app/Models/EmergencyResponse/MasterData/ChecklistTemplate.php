<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistTemplate extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_checklist_templates';

    protected $fillable = [
        'code', 'name', 'applies_to', 'equipment_category_id', 'safety_device_type_id',
        'description', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(ChecklistTemplateItem::class)->orderBy('sort_order');
    }

    public function equipmentCategory()
    {
        return $this->belongsTo(EquipmentCategory::class);
    }

    public function safetyDeviceType()
    {
        return $this->belongsTo(SafetyDeviceType::class);
    }
}
