<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Reference;

use App\Enums\ControlRoomSiteCode;
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
     * @return Collection<int, Employee>
     */
    public function all(ControlRoomSiteCode $site): Collection
    {
        return Cache::remember(
            "control-room:personnel:{$site->value}",
            self::CACHE_TTL_SECONDS,
            fn (): Collection => Employee::query()
                ->where('site_dedicated', $site->sourceKey())
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
