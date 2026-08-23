<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Maintenance;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkOrderSparePart extends Model
{
    use HasUuids;

    protected $table = 'er_work_order_spare_parts';

    protected $fillable = ['work_order_id', 'spare_part_id', 'quantity_used', 'unit_cost_snapshot', 'notes'];

    protected $casts = [
        'quantity_used' => 'integer',
        'unit_cost_snapshot' => 'decimal:2',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }

    public function subtotal(): float
    {
        return (float) $this->quantity_used * (float) ($this->unit_cost_snapshot ?? 0);
    }
}
