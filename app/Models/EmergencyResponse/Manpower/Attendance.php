<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Manpower;

use App\Models\EmergencyResponse\MasterData\Shift;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasUuids;

    protected $table = 'er_attendance';

    public const STATUSES = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'cuti' => 'Cuti'];

    protected $fillable = [
        'employee_id', 'shift_id', 'date', 'status', 'check_in_at', 'check_out_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
