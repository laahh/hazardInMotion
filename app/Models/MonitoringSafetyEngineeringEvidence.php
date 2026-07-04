<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MonitoringSafetyEngineeringPhase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringSafetyEngineeringEvidence extends Model
{
    protected $table = 'monitoring_safety_engineering_evidences';

    protected $fillable = [
        'record_id',
        'phase',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'phase' => MonitoringSafetyEngineeringPhase::class,
            'file_size' => 'integer',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(MonitoringSafetyEngineeringRecord::class, 'record_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
