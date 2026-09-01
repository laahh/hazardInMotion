<?php

declare(strict_types=1);

namespace App\Models\Isc;

use Illuminate\Database\Eloquent\Model;

final class IscDetectionRule extends Model
{
    protected $table = 'isc_detection_rules';

    protected $fillable = ['code', 'name', 'is_active', 'stale_gps_seconds', 'notify_channel'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'stale_gps_seconds' => 'integer',
    ];
}
