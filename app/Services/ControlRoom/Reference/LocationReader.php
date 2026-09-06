<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Reference;

use App\Enums\ControlRoomSiteCode;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Query langsung ke Postgres bcbeats.m_lokasi (hierarki site/lokasi/detil),
 * pola diambil dari App\Services\PembatasanLV\PembatasanLVSiteLokasiService.
 * TIDAK reuse LokasiNonKritisService/ClickHouse — ditolak eksplisit oleh user
 * untuk modul ini. Lihat plan-OCR.md 0.5 poin 4.
 *
 * isCritical() — bcbeats terbukti tidak punya kolom flag kritis (m_lokasi,
 * bep_vw_site_lokasi_detil_lokasi, dan m_pja* semua dicek, nihil — lihat
 * plan-OCR.md 0.5 poin 7). Sumber kekritisan yang dipakai: pola CONTAINS()
 * pada nama lokasi/detil_lokasi itu sendiri (persis rumus Tableau existing
 * yang diberikan user), divalidasi terhadap data nyata 2026-09-06 — 1.482
 * dari 8.684 baris di bep_vw_site_lokasi_detil_lokasi cocok pola ini
 * (mis. lokasi "(B7) Area Kritis Blok 7", "Aktivitas Area High Risk",
 * detil_lokasi "Area Pengeboran" di bawah lokasi "LATI").
 */
final class LocationReader implements LocationReaderContract
{
    private const CACHE_TTL_SECONDS = 600;

    private const CACHE_KEY = 'control-room:locations:m_lokasi';

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    public function all(ControlRoomSiteCode $site): Collection
    {
        return $this->fetchAll()
            ->filter(fn (array $row): bool => $row['site'] === $site->sourceKey())
            ->values();
    }

    /**
     * @return array{site: string, lokasi: string, detail_lokasi: string}|null
     */
    public function find(string $lokasi, string $detilLokasi): ?array
    {
        return $this->fetchAll()->first(
            fn (array $row): bool => $this->normalize($row['lokasi']) === $this->normalize($lokasi)
                && $this->normalize($row['detail_lokasi']) === $this->normalize($detilLokasi)
        );
    }

    /**
     * Pola CONTAINS() dari Tableau existing (dikonfirmasi user 2026-09-06),
     * dicek murni sebagai string match — tidak perlu query tambahan karena
     * lokasi/detilLokasi sudah jadi parameter method ini. Keyword dikonfigurasi
     * di config('control-room.critical_area_keywords') supaya bisa disesuaikan
     * tanpa ubah kode kalau daftar areanya berubah.
     */
    public function isCritical(string $lokasi, string $detilLokasi): bool
    {
        $normalizedLokasi = $this->normalize($lokasi);
        $normalizedDetil = $this->normalize($detilLokasi);

        foreach ((array) config('control-room.critical_area_keywords.lokasi', []) as $keyword) {
            if ($keyword !== '' && str_contains($normalizedLokasi, mb_strtolower((string) $keyword))) {
                return true;
            }
        }

        foreach ((array) config('control-room.critical_area_keywords.detil_lokasi', []) as $keyword) {
            if ($keyword !== '' && str_contains($normalizedDetil, mb_strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    public function criticalAreas(ControlRoomSiteCode $site): Collection
    {
        return $this->all($site)->filter(
            fn (array $row): bool => $this->isCritical($row['lokasi'], $row['detail_lokasi'])
        )->values();
    }

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    private function fetchAll(): Collection
    {
        $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            if (! $this->olap->isReachable()) {
                Log::warning('LocationReader: Postgres OLAP (pgsql_direct/pgsql_ssh) tidak terjangkau, mengembalikan collection kosong.');

                return [];
            }

            $sql = <<<'SQL'
                SELECT
                    TRIM(site.nama) AS site,
                    TRIM(lokasi.nama) AS lokasi,
                    TRIM(detil.nama) AS detail_lokasi
                FROM bcbeats.m_lokasi site
                JOIN bcbeats.m_lokasi lokasi
                    ON lokasi.id_parent = site.id
                   AND lokasi.id_tipe = 200
                   AND lokasi.is_active = '1'
                JOIN bcbeats.m_lokasi detil
                    ON detil.id_parent = lokasi.id
                   AND detil.id_tipe = 300
                   AND detil.is_active = '1'
                WHERE site.id_tipe = 100
                  AND site.is_active = '1'
                SQL;

            return collect($this->olap->select($sql, [], 3000))
                ->map(fn (object $row): array => [
                    'site' => trim((string) ($row->site ?? '')),
                    'lokasi' => trim((string) ($row->lokasi ?? '')),
                    'detail_lokasi' => trim((string) ($row->detail_lokasi ?? '')),
                ])
                ->filter(fn (array $row): bool => $row['lokasi'] !== '')
                ->values()
                ->all();
        });

        return collect($rows);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
