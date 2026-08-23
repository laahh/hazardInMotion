<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base for master-data tables shaped as code/name/level/color/description
 * (severity levels, priority levels) — used to render consistent badges.
 */
abstract class LeveledLookup extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $fillable = [
        'code', 'name', 'level', 'color', 'description', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
    ];
}
