<?php

declare(strict_types=1);

namespace App\Models\Isc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IscInterventionEvidence extends Model
{
    protected $table = 'isc_intervention_evidences';

    protected $fillable = ['intervention_id', 'path', 'original_name', 'uploaded_by'];

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(IscIntervention::class, 'intervention_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
