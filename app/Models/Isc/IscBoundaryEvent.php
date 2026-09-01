<?php

declare(strict_types=1);

namespace App\Models\Isc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class IscBoundaryEvent extends Model
{
    protected $table = 'isc_boundary_events';

    protected $fillable = [
        'person_key', 'entity', 'sid', 'name', 'company', 'job_title', 'lat', 'lng',
        'iupk_site', 'hazard_boundary_id', 'hazard_name', 'entered_at',
        'exited_at', 'duration_seconds', 'status', 'rule_code',
        'besigma_violation_id', 'user_id', 'unit_id', 'hazard_kind', 'besigma_status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'entered_at' => 'datetime',
        'exited_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function interventions(): HasMany
    {
        return $this->hasMany(IscIntervention::class, 'event_id');
    }

    public function latestIntervention(): HasOne
    {
        return $this->hasOne(IscIntervention::class, 'event_id')->latestOfMany();
    }

    public function durationSecondsNow(): int
    {
        if ($this->duration_seconds !== null && $this->exited_at !== null) {
            return (int) $this->duration_seconds;
        }
        if ($this->entered_at === null) {
            return 0;
        }

        return (int) $this->entered_at->diffInSeconds(now());
    }
}
