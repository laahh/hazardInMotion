<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Reference;

use App\Enums\ControlRoomSiteCode;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Query langsung ke Postgres bcbeats.m_lokasi (hierarki site/lokasi/detil),
 * pola diambil dari App\Services\PembatasanLV\PembatasanLVSiteLokasiService.
 * TIDAK reuse LokasiNonKritisService/ClickHouse — ditolak eksplisit oleh user
 * untuk modul ini. Lihat plan-OCR.md 0.5 poin 4.
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
     * BLOCKED — lihat plan-OCR.md 0.5 poin 4 dan Lampiran D pertanyaan #26.
     *
     * Kolom flag area kritis belum ditemukan di `bcbeats.m_lokasi` maupun
     * `bcbeats.bep_vw_site_lokasi_detil_lokasi` — query yang sudah terbukti
     * jalan (PembatasanLVSiteLokasiService, dipakai ulang di fetchAll() di
     * bawah) tidak pernah men-select kolom itu.
     *
     * JANGAN isi method ini dengan tebakan nama kolom, dan JANGAN diam-diam
     * fallback ke ClickHouse/LokasiNonKritisService — itu sudah ditolak
     * eksplisit oleh user sebagai sumber untuk modul ini. Tunggu hasil
     * `\d+ bcbeats.m_lokasi` / `\d+ bcbeats.bep_vw_site_lokasi_detil_lokasi`,
     * lalu tambahkan kolom itu ke SELECT di fetchAll() sebelum
     * mengimplementasikan method ini.
     */
    public function isCritical(string $lokasi, string $detilLokasi): bool
    {
        throw new RuntimeException(
            'LocationReader::isCritical() belum bisa diimplementasikan — kolom flag area kritis '.
            'di bcbeats.m_lokasi/bcbeats.bep_vw_site_lokasi_detil_lokasi belum ditemukan. '.
            'Lihat plan-OCR.md Lampiran D pertanyaan #26.'
        );
    }

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    public function criticalAreas(ControlRoomSiteCode $site): Collection
    {
        throw new RuntimeException(
            'LocationReader::criticalAreas() belum bisa diimplementasikan — lihat plan-OCR.md Lampiran D pertanyaan #26.'
        );
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
