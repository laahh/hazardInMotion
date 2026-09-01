<?php

declare(strict_types=1);

namespace App\Models\Isc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IscInterventionVerification extends Model
{
    protected $table = 'isc_intervention_verifications';

    protected $fillable = ['intervention_id', 'verifier_user_id', 'result', 'notes'];

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(IscIntervention::class, 'intervention_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_user_id');
    }
}
