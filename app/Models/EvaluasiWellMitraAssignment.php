<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapping user Admin → scope Mitra Kerja (site + perusahaan) untuk Evaluasi Well.
 *
 * @property int $id
 * @property int $user_id
 * @property string $site
 * @property string $perusahaan
 * @property bool $is_active
 */
class EvaluasiWellMitraAssignment extends Model
{
    protected $table = 'evaluasi_well_mitra_assignments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'site',
        'perusahaan',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
