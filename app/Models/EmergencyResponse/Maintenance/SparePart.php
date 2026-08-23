<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Maintenance;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SparePart extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_spare_parts';

    protected $fillable = [
        'code', 'name', 'unit', 'unit_cost', 'stock_quantity', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
    ];
}
