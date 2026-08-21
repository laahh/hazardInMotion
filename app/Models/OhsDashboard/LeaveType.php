<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $table = 'ohs_leave_types';

    public $timestamps = false;

    protected $fillable = [
        'leave_type',
        'available_days',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'LeaveType' => $this->leave_type,
            'AvailableDays' => $this->available_days ?? '',
        ];
    }
}
