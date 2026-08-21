<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $table = 'ohs_employees';

    protected $primaryKey = 'emp_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'emp_id',
        'sid',
        'emp_name',
        'position',
        'team',
        'site_dedicated',
        'company',
        'photo_url',
    ];

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'emp_id', 'emp_id');
    }

    public function backupLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'backup_emp_id', 'emp_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'EmpId' => $this->emp_id,
            'SID' => $this->sid ?? '',
            'EmpName' => $this->emp_name,
            'Position' => $this->position ?? '',
            'Team' => $this->team ?? '',
            'SiteDedicated' => $this->site_dedicated ?? '',
            'Company' => $this->company ?? '',
            'PhotoUrl' => $this->photo_url ?? '',
        ];
    }
}
