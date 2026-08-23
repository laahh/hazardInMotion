<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Incident;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IncidentEquipmentUsage extends Model
{
    use HasUuids;

    protected $table = 'er_incident_equipment_usage';

    protected $fillable = [
        'incident_id', 'response_unit_id', 'equipmentable_type', 'equipmentable_id',
        'quantity_used', 'condition_after', 'notes', 'created_by',
    ];

    protected $casts = [
        'quantity_used' => 'integer',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function responseUnit()
    {
        return $this->belongsTo(ResponseUnit::class);
    }

    public function equipmentable(): MorphTo
    {
        return $this->morphTo();
    }
}
