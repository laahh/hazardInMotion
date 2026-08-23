<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Manpower;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmployeeTraining extends Model
{
    use HasUuids;

    protected $table = 'er_employee_trainings';

    protected $fillable = [
        'employee_id', 'training_id', 'provider', 'trained_at', 'score', 'is_passed',
        'certificate_path', 'expires_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'trained_at' => 'date',
        'expires_at' => 'date',
        'is_passed' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function statusLabel(): string
    {
        if (! $this->is_passed) {
            return 'Tidak Lulus';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'Expired';
        }
        if ($this->expires_at && $this->expires_at->diffInDays(now()) <= 30 && $this->expires_at->isFuture()) {
            return 'Akan Expired';
        }

        return 'Valid';
    }
}
