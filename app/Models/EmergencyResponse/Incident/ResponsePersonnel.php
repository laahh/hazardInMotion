<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Incident;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ResponsePersonnel extends Model
{
    use HasUuids;

    protected $table = 'er_response_personnel';

    protected $fillable = [
        'incident_id', 'response_unit_id', 'user_id', 'role_in_response', 'departed_at', 'arrived_at', 'notes',
    ];

    protected $casts = [
        'departed_at' => 'datetime',
        'arrived_at' => 'datetime',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function responseUnit()
    {
        return $this->belongsTo(ResponseUnit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
