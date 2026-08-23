<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Shared;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EquipmentStatusHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_equipment_status_histories';

    protected $fillable = [
        'trackable_type', 'trackable_id', 'field_changed', 'old_value', 'new_value', 'notes', 'changed_by', 'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
