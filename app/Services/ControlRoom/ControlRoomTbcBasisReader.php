<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Basis TBC: Hazard & Inspeksi dari bcbeats.aaj_car_all_year_from_dav.
 * Nomor tasklist (kolom id) yang dicocokkan ke GSheet validasi TBC.
 * % SAP / Pareto / GR tetap dari mv_inspeksi_hazard — hanya TBC yang pindah.
 *
 * Nama kolom mengikuti pemakaian tabel yang sama di ClickHouse
 * (MapBaseController, TasklistController). Kalau skema Postgres berbeda,
 * cukup ubah konstanta COL_* di bawah.
 */
final class ControlRoomTbcBasisReader
{
    public const TABLE = 'bcbeats.aaj_car_all_year_from_dav';

    private const COL_ID = 'id';

    private const COL_SID = 'sid_pelapor';

    private const COL_AT = 'tanggal_pembuatan';

    private const COL_TYPE = 'jenis_laporan';

    private const COL_TOOLS = 'name_tools_observation';

    private const SID_CHUNK = 40;

    private const CACHE_SECONDS = 300;

    private const QUERY_TIMEOUT_MS = 8000;

    public const CARD_LIMIT = 80;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * Temuan Hazard/Inspeksi SID-SID ini dalam [start, end). Belum difilter
     * jendela jaga — penyaringan per tanggal dilakukan pemanggil.
     *
     * @param  list<string>  $sids
     * @return array{loaded: bool, findings: list<array<string, mixed>>}
     */
    public function findingsForSids(array $sids, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $sids = $this->normalizeSids($sids);
        if ($sids === []) {
            return ['loaded' => true, 'findings' => []];
        }

        $cacheKey = 'control-room:tbc-basis:v1:'.hash(
            'sha1',
            implode(',', $sids).'|'.$start->toDateTimeString().'|'.$end->toDateTimeString(),
        );
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return ['loaded' => true, 'findings' => $cached];
        }

        if (! $this->olap->isReachable()) {
            return ['loaded' => false, 'findings' => []];
        }

        $findings = [];
        foreach (array_chunk($sids, self::SID_CHUNK) as $chunk) {
            $rows = $this->fetch($chunk, $start, $end, null);
            if ($rows === null) {
                return ['loaded' => false, 'findings' => []];
            }
            foreach ($rows as $row) {
                $findings[] = $this->finding($row);
            }
        }

        Cache::put($cacheKey, $findings, self::CACHE_SECONDS);

        return ['loaded' => true, 'findings' => $findings];
    }

    /**
     * Kartu tab TBC di modal Detail (format sama dengan ControlRoomSapDutyReader).
     *
     * @return list<array<string, mixed>>|null  null = query gagal
     */
    public function cardsForSid(string $sid, CarbonImmutable $start, CarbonImmutable $end): ?array
    {
        $sids = $this->normalizeSids([$sid]);
        if ($sids === [] || ! $this->olap->isReachable()) {
            return null;
        }

        $rows = $this->fetch($sids, $start, $end, self::CARD_LIMIT);
        if ($rows === null) {
            return null;
        }

        return array_map(fn (object $row): array => $this->card($row), $rows);
    }

    /**
     * @param  list<string>  $sids
     * @return list<object>|null
     */
    private function fetch(array $sids, CarbonImmutable $start, CarbonImmutable $end, ?int $limit): ?array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $tools = ControlRoomInspeksiHazardToolFilter::sqlPredicate(self::COL_TOOLS);
        $limitSql = $limit === null ? '' : 'LIMIT '.(int) $limit;
        $sql = sprintf(
            "SELECT DISTINCT ON (%1\$s)
                    CAST(%1\$s AS text) AS report_id,
                    CAST(%2\$s AS text) AS sid,
                    %3\$s AS at,
                    UPPER(BTRIM(COALESCE(%4\$s, ''))) AS jenis,
                    COALESCE(%5\$s, '') AS tools,
                    COALESCE(deskripsi, '') AS description,
                    COALESCE(subketidaksesuaian, '') AS category,
                    COALESCE(status, '') AS status,
                    COALESCE(nama_lokasi, '') AS lokasi,
                    COALESCE(nama_detail_lokasi, '') AS detil_lokasi,
                    COALESCE(nama_pelapor, '') AS reporter,
                    COALESCE(perusahaan_pelapor, '') AS company
             FROM %6\$s
             WHERE %2\$s IN (%7\$s)
               AND %3\$s >= CAST(? AS timestamp)
               AND %3\$s < CAST(? AS timestamp)
               AND UPPER(BTRIM(COALESCE(%4\$s, ''))) IN ('HAZARD', 'INSPEKSI')
               AND %8\$s
             ORDER BY %1\$s, %3\$s
             %9\$s",
            self::COL_ID,
            self::COL_SID,
            self::COL_AT,
            self::COL_TYPE,
            self::COL_TOOLS,
            self::TABLE,
            $placeholders,
            $tools['sql'],
            $limitSql,
        );
        $bindings = [...$sids, $start->toDateTimeString(), $end->toDateTimeString(), ...$tools['bindings']];

        try {
            return $this->olap->select($sql, $bindings, self::QUERY_TIMEOUT_MS);
        } catch (Throwable $e) {
            Log::warning('ControlRoom TBC basis ('.self::TABLE.') gagal: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(object $row): array
    {
        return [
            'sid' => strtoupper(trim((string) ($row->sid ?? ''))),
            'at' => $this->time($row->at ?? null)?->format('Y-m-d H:i:s') ?? '',
            'component' => $this->component((string) ($row->jenis ?? '')),
            'report_id' => trim((string) ($row->report_id ?? '')),
            'category' => trim((string) ($row->category ?? '')),
            'description' => trim((string) ($row->description ?? '')),
            'company' => trim((string) ($row->company ?? '')),
            'pic' => '',
            'status' => trim((string) ($row->status ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(object $row): array
    {
        $jenis = (string) ($row->jenis ?? '');
        $tools = trim((string) ($row->tools ?? ''));
        $at = $this->time($row->at ?? null);

        return [
            'id' => trim((string) ($row->report_id ?? '')) ?: '—',
            'type' => $this->component($jenis),
            'type_label' => $jenis !== '' ? $jenis : 'HAZARD',
            'headline' => $tools !== '' ? $jenis.' - '.$tools : $jenis,
            'submitted_at' => $at?->format('Y-m-d H:i:s') ?? '',
            'submitted_label' => $at?->format('Y-m-d H:i:s') ?? '—',
            'geotag' => null,
            'subcategory' => $this->text($row->category ?? null),
            'description' => $this->text($row->description ?? null),
            'pic' => '',
            'pic_meta' => '—',
            'reporter' => $this->text($row->reporter ?? null),
            'reporter_meta' => $this->text($row->company ?? null),
            'location' => $this->text($row->lokasi ?? null),
            'location_detail' => $this->text($row->detil_lokasi ?? null),
            'status' => $this->text($row->status ?? null),
            'photo_url' => null,
            'photo_page_id' => null,
            'photo_page_kind' => null,
        ];
    }

    private function component(string $jenis): string
    {
        return str_contains(strtoupper($jenis), 'INSPEKSI') ? 'inspeksi' : 'hazard';
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function text(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '—';
    }

    /**
     * @param  list<string>  $sids
     * @return list<string>
     */
    private function normalizeSids(array $sids): array
    {
        $clean = [];
        foreach ($sids as $sid) {
            $sid = strtoupper(trim((string) $sid));
            if ($sid !== '') {
                $clean[$sid] = $sid;
            }
        }
        $clean = array_values($clean);
        sort($clean);

        return $clean;
    }
}
