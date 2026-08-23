<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Incident;

use App\Models\EmergencyResponse\MasterData\EmergencyUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ResponseUnit extends Model
{
    use HasUuids;

    public const STATUSES = ['dispatched' => 'Dispatched', 'arrived' => 'Arrived', 'returned' => 'Returned'];

    protected $table = 'er_response_units';

    protected $fillable = [
        'incident_id', 'emergency_unit_id', 'status', 'departed_at', 'arrived_at', 'returned_at',
        'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'departed_at' => 'datetime',
        'arrived_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function emergencyUnit()
    {
        return $this->belongsTo(EmergencyUnit::class);
    }

    public function personnel()
    {
        return $this->hasMany(ResponsePersonnel::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
