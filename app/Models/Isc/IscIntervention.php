<?php

declare(strict_types=1);

namespace App\Models\Isc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class IscIntervention extends Model
{
    protected $table = 'isc_interventions';

    protected $fillable = ['event_id', 'pic_user_id', 'type', 'notes', 'status'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(IscBoundaryEvent::class, 'event_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(IscInterventionEvidence::class, 'intervention_id');
    }

    public function verification(): HasOne
    {
        return $this->hasOne(IscInterventionVerification::class, 'intervention_id');
    }
}
