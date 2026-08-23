<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base for master-data lookup tables that all share the same
 * code/name/description/is_active shape (departments, emergency units,
 * equipment categories, safety device types, incident types, maintenance
 * types, training types, certification types).
 */
abstract class SimpleLookup extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
