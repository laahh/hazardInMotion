<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Manpower;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmployeeCertification extends Model
{
    use HasUuids;

    protected $table = 'er_employee_certifications';

    protected $fillable = [
        'employee_id', 'certification_id', 'certificate_number', 'issuing_body', 'issued_at',
        'expires_at', 'certificate_path', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function certification()
    {
        return $this->belongsTo(Certification::class);
    }

    public function statusLabel(): string
    {
        if (! $this->expires_at) {
            return 'Valid';
        }
        if ($this->expires_at->isPast()) {
            return 'Expired';
        }
        if ($this->expires_at->diffInDays(now()) <= 30) {
            return 'Akan Expired';
        }

        return 'Valid';
    }
}
