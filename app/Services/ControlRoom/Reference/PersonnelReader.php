<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Reference;

use App\Models\OhsDashboard\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Wrapper tipis di atas App\Models\OhsDashboard\Employee — TIDAK duplikasi
 * query ke bcsid.bep_vw_safety_karyawan_aktif. Lihat plan-OCR.md 0.5 poin 3.
 */
final class PersonnelReader
{
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Semua karyawan aktif dari seluruh site (bukan hanya site filter halaman).
     * Filter site di jadwal/absen hanya membatasi kalender & record, bukan picker personil.
     *
     * @return Collection<int, Employee>
     */
    public function all(): Collection
    {
        return Cache::remember(
            'control-room:personnel:all',
            self::CACHE_TTL_SECONDS,
            fn (): Collection => Employee::query()
                ->select(['emp_id', 'sid', 'emp_name', 'site_dedicated'])
                ->orderBy('emp_name')
                ->get()
        );
    }

    public function find(string $sourceKey): ?Employee
    {
        return Employee::query()->where('sid', $sourceKey)->first();
    }

    /**
     * Employee (bep_vw_safety_karyawan_aktif) sudah pre-filtered ke
     * status_karyawan = 'AKTIF' di sumbernya — ditemukan berarti aktif.
     */
    public function existsAndActive(string $sourceKey): bool
    {
        return $this->find($sourceKey) !== null;
    }
}
