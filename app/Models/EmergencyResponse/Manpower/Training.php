<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Manpower;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\EmergencyResponse\MasterData\TrainingType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Training extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_trainings';

    protected $fillable = [
        'code', 'name', 'training_type_id', 'provider', 'default_validity_months', 'description',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'default_validity_months' => 'integer',
        'is_active' => 'boolean',
    ];

    public function type()
    {
        return $this->belongsTo(TrainingType::class, 'training_type_id');
    }
}
