<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Maintenance;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaintenanceHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_maintenance_histories';

    protected $fillable = [
        'work_order_id', 'target_type', 'target_id', 'work_type', 'summary', 'total_cost', 'technician_id', 'completed_at',
    ];

    protected $casts = [
        'total_cost' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
