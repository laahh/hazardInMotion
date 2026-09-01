<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Mapping user Admin → scope Mitra Kerja (multi perusahaan + multi site) untuk Evaluasi Well.
 *
 * Kolom `site` dan `perusahaan` adalah denormalisasi pasangan pertama (kompatibilitas).
 * Sumber kebenaran: relasi `scopes`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $site
 * @property string $perusahaan
 * @property bool $is_active
 * @property Collection<int, EvaluasiWellMitraAssignmentScope> $scopes
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

    public function scopes(): HasMany
    {
        return $this->hasMany(EvaluasiWellMitraAssignmentScope::class, 'evaluasi_well_mitra_assignment_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Perusahaan + daftar site-nya, untuk form dan tampilan.
     *
     * @return list<array{perusahaan: string, sites: list<string>}>
     */
    public function groupedCompanySites(): array
    {
        $grouped = [];

        foreach ($this->scopes as $scope) {
            $company = trim((string) $scope->perusahaan);
            $site = trim((string) $scope->site);
            if ($company === '' || $site === '') {
                continue;
            }

            $grouped[$company] ??= [];
            $grouped[$company][$site] = $site;
        }

        if ($grouped === []) {
            $company = trim((string) $this->perusahaan);
            $site = trim((string) $this->site);
            if ($company !== '' && $site !== '') {
                $grouped[$company] = [$site => $site];
            }
        }

        $rows = [];
        foreach ($grouped as $company => $sites) {
            $siteList = array_values($sites);
            sort($siteList, SORT_STRING);
            $rows[] = [
                'perusahaan' => $company,
                'sites' => $siteList,
            ];
        }

        return $rows;
    }
}
