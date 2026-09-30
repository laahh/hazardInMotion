<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pasangan perusahaan + site yang dipegang oleh seorang PIC Monitoring Safety Engineering.
 *
 * @property int $id
 * @property int $assignment_id
 * @property string $perusahaan
 * @property string $site
 */
class MonitoringSafetyEngineeringPicAssignmentScope extends Model
{
    protected $table = 'monitoring_safety_engineering_pic_assignment_scopes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assignment_id',
        'perusahaan',
        'site',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(
            MonitoringSafetyEngineeringPicAssignment::class,
            'assignment_id',
        );
    }
}
