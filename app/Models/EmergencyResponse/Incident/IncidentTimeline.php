<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Incident;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IncidentTimeline extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_incident_timelines';

    protected $fillable = ['incident_id', 'event_type', 'description', 'is_internal', 'created_by', 'created_at'];

    protected $casts = [
        'is_internal' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
