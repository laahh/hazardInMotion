<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisoryCriticalAreaAlertLog extends Model
{
    public const STATUS_SUDAH_DI_INTERVENSI = 'sudah_di_intervensi';

    public const STATUS_BELUM_DI_INTERVENSI = 'belum_di_intervensi';

    protected $table = 'supervisory_critical_area_alert_log';

    protected $fillable = [
        'tanggal',
        'dop_id',
        'has_observasi',
        'status_intervensi',
        'temuan',
        'dop_snapshot',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'has_observasi' => 'boolean',
        'dop_snapshot' => 'array',
    ];

    public function dailyOperationPlan(): BelongsTo
    {
        return $this->belongsTo(DailyOperationPlan::class, 'dop_id');
    }
}
