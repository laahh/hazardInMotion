<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Incident;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IncidentStatusHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_incident_status_histories';

    protected $fillable = ['incident_id', 'from_status', 'to_status', 'notes', 'changed_by', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
