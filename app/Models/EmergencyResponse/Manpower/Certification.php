<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Manpower;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\EmergencyResponse\MasterData\CertificationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certification extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_certifications';

    protected $fillable = [
        'code', 'name', 'certification_type_id', 'issuing_body', 'default_validity_months',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'default_validity_months' => 'integer',
        'is_active' => 'boolean',
    ];

    public function type()
    {
        return $this->belongsTo(CertificationType::class, 'certification_type_id');
    }
}
