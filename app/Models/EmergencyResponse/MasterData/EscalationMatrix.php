<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EscalationMatrix extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_escalation_matrices';

    protected $fillable = [
        'name', 'applies_to', 'level', 'delay_minutes', 'notify_role_id',
        'channel', 'description', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'level' => 'integer',
        'delay_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function notifyRole()
    {
        return $this->belongsTo(Role::class, 'notify_role_id');
    }
}
