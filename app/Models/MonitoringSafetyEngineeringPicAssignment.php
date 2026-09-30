<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Mapping orang (nama / SID, opsional tertaut ke user Admin) → scope perusahaan + site
 * untuk Monitoring Safety Engineering.
 *
 * Satu orang boleh memegang banyak perusahaan, dan tiap perusahaan boleh punya banyak site.
 * Sumber kebenaran scope adalah relasi `scopeRows`.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $nama
 * @property string|null $sid
 * @property string|null $jabatan
 * @property bool $is_active
 * @property string|null $catatan
 * @property Collection<int, MonitoringSafetyEngineeringPicAssignmentScope> $scopeRows
 */
class MonitoringSafetyEngineeringPicAssignment extends Model
{
    /**
     * Sentinel site yang berarti "semua site" dalam perusahaan tersebut.
     */
    public const ALL_SITES = 'ALL SITE';

    protected $table = 'monitoring_safety_engineering_pic_assignments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nama',
        'sid',
        'jabatan',
        'is_active',
        'catatan',
        'created_by',
        'updated_by',
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

    /**
     * Dinamai `scopeRows` (bukan `scopes`) supaya tidak bentrok dengan query scope Eloquent.
     */
    public function scopeRows(): HasMany
    {
        return $this->hasMany(
            MonitoringSafetyEngineeringPicAssignmentScope::class,
            'assignment_id',
        );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
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

        foreach ($this->scopeRows as $scope) {
            $company = trim((string) $scope->perusahaan);
            $site = trim((string) $scope->site);
            if ($company === '' || $site === '') {
                continue;
            }

            $grouped[$company] ??= [];
            $grouped[$company][$site] = $site;
        }

        $rows = [];
        foreach ($grouped as $company => $sites) {
            $siteList = array_values($sites);
            sort($siteList, SORT_STRING);
            $rows[] = [
                'perusahaan' => (string) $company,
                'sites' => $siteList,
            ];
        }

        return $rows;
    }

    /**
     * Label identitas untuk tampilan: "NAMA (SID)".
     */
    public function identityLabel(): string
    {
        $nama = trim($this->nama);
        $sid = trim((string) $this->sid);

        if ($sid === '') {
            return $nama;
        }

        return $nama === '' ? $sid : $nama . ' (' . $sid . ')';
    }

    public static function isAllSitesLabel(string $site): bool
    {
        $key = strtolower(trim(preg_replace('/\s+/', ' ', $site) ?? $site));

        return in_array($key, ['all site', 'all sites', 'allsite', 'semua site', 'all'], true);
    }
}
