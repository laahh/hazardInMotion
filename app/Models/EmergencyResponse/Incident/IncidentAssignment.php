<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Incident;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IncidentAssignment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_incident_assignments';

    protected $fillable = ['incident_id', 'user_id', 'role_note', 'assigned_by', 'assigned_at'];

    protected $casts = ['assigned_at' => 'datetime'];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
