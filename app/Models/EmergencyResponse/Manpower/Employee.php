<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Manpower;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\EmergencyUnit;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_employees';

    public const EMPLOYMENT_STATUSES = ['permanent' => 'Tetap', 'contract' => 'Kontrak', 'vendor' => 'Vendor'];

    protected $fillable = [
        'employee_number', 'user_id', 'full_name', 'photo_path', 'position', 'department_id',
        'emergency_unit_id', 'site_id', 'email', 'phone', 'employment_status', 'skills',
        'emergency_role', 'is_active', 'emergency_contact_name', 'emergency_contact_phone',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected array $auditExcept = ['photo_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function emergencyUnit()
    {
        return $this->belongsTo(EmergencyUnit::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class)->orderByDesc('date');
    }

    public function trainings()
    {
        return $this->hasMany(EmployeeTraining::class)->orderByDesc('trained_at');
    }

    public function certifications()
    {
        return $this->hasMany(EmployeeCertification::class)->orderByDesc('issued_at');
    }

    public function employmentStatusLabel(): string
    {
        return self::EMPLOYMENT_STATUSES[$this->employment_status] ?? $this->employment_status;
    }
}
