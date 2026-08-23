<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sla extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_slas';

    protected $fillable = [
        'code', 'name', 'applies_to', 'response_time_minutes', 'resolution_time_minutes',
        'description', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'response_time_minutes' => 'integer',
        'resolution_time_minutes' => 'integer',
        'is_active' => 'boolean',
    ];
}
