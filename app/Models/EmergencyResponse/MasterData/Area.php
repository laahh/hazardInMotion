<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_areas';

    protected $fillable = [
        'location_id', 'code', 'name', 'description', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
