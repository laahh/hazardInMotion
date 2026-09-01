<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pasangan perusahaan + site dalam assignment Mitra Kerja.
 *
 * @property int $id
 * @property int $evaluasi_well_mitra_assignment_id
 * @property string $perusahaan
 * @property string $site
 */
class EvaluasiWellMitraAssignmentScope extends Model
{
    protected $table = 'evaluasi_well_mitra_assignment_scopes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'evaluasi_well_mitra_assignment_id',
        'perusahaan',
        'site',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'evaluasi_well_mitra_assignment_id' => 'integer',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EvaluasiWellMitraAssignment::class, 'evaluasi_well_mitra_assignment_id');
    }
}
