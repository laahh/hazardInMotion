<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringSafetyEngineeringMasterAktivitas extends Model
{
    protected $table = 'monitoring_safety_engineering_master_aktivitas';

    protected $fillable = [
        'site',
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
