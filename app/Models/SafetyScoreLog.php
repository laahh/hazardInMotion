<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SafetyScoreLog extends Model
{
    use HasFactory;

    protected $table = 'safety_score_logs';

    protected $fillable = [
        'driver_id',
        'trip_id',
        'timestamp',
        'ear',
        'perclos_60s',
        'blink_60s',
        'microsleep_60s',
        'fatigue',
        'drift',
        'safety_score',
        'status',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'ear' => 'decimal:6',
        'perclos_60s' => 'decimal:6',
        'blink_60s' => 'integer',
        'microsleep_60s' => 'integer',
        'fatigue' => 'decimal:6',
        'drift' => 'decimal:6',
        'safety_score' => 'decimal:6',
    ];
}

