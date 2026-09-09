<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Laporan SAP (hazard, inspeksi, observasi, OAK) yang disubmit pelapor
 * di jendela jaga Control Room. Query langsung ke MV OBDS — bukan snapshot.
 */
final class ControlRoomSapDutyReader
{
    public const PER_TYPE_LIMIT = 40;

    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * Hari H (tanggal jaga) sampai akhir H+1, supaya laporan yang disubmit
     * keesokan hari tetap masuk.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function reportingWindow(CarbonInterface $dutyDate): array
    {
        $start = CarbonImmutable::parse($dutyDate)->startOfDay();

        return [
            'start' => $start,
            'end' => $start->addDays(2),
        ];
    }

    /**
     * @return array{
     *     sid: string,
     *     date: string,
     *     shift: string,
     *     window_start: string,
     *     window_end: string,
     *     reachable: bool,
     *     truncated: bool,
     *     errors: list<string>,
     *     counts: array{all: int, hazard: int, inspeksi: int, observasi: int, oak: int},
     *     cards: list<array<string, mixed>>
     * }
     */
    public function forDuty(string $sid, CarbonInterface $dutyDate, ControlRoomShiftCode $shift): array
    {
        $sid = strtoupper(trim($sid));
        $window = $this->reportingWindow($dutyDate);
        $meta = [
            'sid' => $sid,
            'date' => CarbonImmutable::parse($dutyDate)->toDateString(),
            'shift' => $shift->value,
            'window_start' => $window['start']->format('Y-m-d H:i'),
            'window_end' => $window['end']->subSecond()->format('Y-m-d H:i'),
        ];

        if ($sid === '') {
            return $this->payload($meta, [], reachable: false, errors: ['SID kosong.']);
        }

        if (! $this->olap->isReachable()) {
            return $this->payload($meta, [], reachable: false, errors: ['Sumber SAP (OBDS) tidak terjangkau.']);
        }

        $cacheKey = 'control-room:sap-duty:v8:'.$sid.':'.$meta['date'];
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['cards'])) {
            return $this->payload($meta, $cached['cards'], reachable: true);
        }

        $errors = [];
        $cards = $this->cardsFromRows(
            $this->fetchHazardInspeksi($sid, $window['start'], $window['end'], $errors),
            $this->fetchObservasi($sid, $window['start'], $window['end'], $errors),
            $this->fetchOak($sid, $window['start'], $window['end'], $errors),
        );

        if ($errors === []) {
            Cache::put($cacheKey, ['cards' => $cards], self::CACHE_SECONDS);
        }

        return $this->payload($meta, $cards, reachable: $errors === [] || $cards !== [], errors: $errors);
    }

    /**
     * @param  list<object>  $hazardRows
     * @param  list<object>  $observasiRows
     * @param  list<object>  $oakRows
     * @return list<array<string, mixed>>
     */
    public function cardsFromRows(array $hazardRows, array $observasiRows, array $oakRows): array
    {
        $cards = [
            ...$this->mapHazardInspeksi($hazardRows),
            ...$this->mapObservasi($observasiRows),
            ...$this->mapOak($oakRows),
        ];

        usort($cards, fn (array $a, array $b): int => strcmp((string) $a['submitted_at'], (string) $b['submitted_at']));

        return $cards;
    }

    /**
     * SID + rentang H/H+1 memakai index kode_sid_pelapor dan tanggal
     * (BitmapAnd). Jangan MATERIALIZED seluruh riwayat SID.
     *
     * @param  list<string>  $errors
     * @return list<object>
     */
    private function fetchHazardInspeksi(string $sid, CarbonImmutable $start, CarbonImmutable $end, array &$errors): array
    {
        $limit = self::PER_TYPE_LIMIT;
        $tools = ControlRoomInspeksiHazardToolFilter::sqlPredicate();
        $sql = "
            SELECT DISTINCT ON (id_laporan)
                   id_laporan, tanggal_laporan, jenis_laporan, status_laporan,
                   deskripsi_temuan, ketidaksesuaian, subketidaksesuaian, tools_observasi,
                   lokasi, detil_lokasi, latitude, longitude,
                   nama_pelapor, jabatan_fungsional_pelapor, perusahaan_pelapor,
                   nama_pic, jabatan_fungsional_pic, perusahaan_pic, url_foto
            FROM bcbeats.mv_inspeksi_hazard
            WHERE kode_sid_pelapor = ?
              AND tanggal_laporan >= CAST(? AS timestamp)
              AND tanggal_laporan < CAST(? AS timestamp)
              AND {$tools['sql']}
            ORDER BY id_laporan, tanggal_laporan
            LIMIT {$limit}
        ";

        return $this->select($sql, [$sid, $start->toDateTimeString(), $end->toDateTimeString(), ...$tools['bindings']], 'hazard/inspeksi', $errors);
    }

    /**
     * @param  list<string>  $errors
     * @return list<object>
     */
    private function fetchObservasi(string $sid, CarbonImmutable $start, CarbonImmutable $end, array &$errors): array
    {
        $limit = self::PER_TYPE_LIMIT;
        $sql = "
            SELECT DISTINCT ON (id_observasi)
                   id_observasi, tanggal_observasi, jenis_kegiatan, catatan_observasi, tools_observasi,
                   lokasi, detil_lokasi, latitude, longitude, url_foto,
                   nama_pelapor, jabatan_fungsional_pelapor, perusahaan_pelapor,
                   nama_personil_diobservasi, perusahaan_personil_diobservasi,
                   jabatan_fungsional_personil_diobservasi
            FROM bcbeats.mv_observasi
            WHERE kode_sid_pelapor = ?
              AND tanggal_observasi >= CAST(? AS timestamp)
              AND tanggal_observasi < CAST(? AS timestamp)
            ORDER BY id_observasi, tanggal_observasi
            LIMIT {$limit}
        ";

        return $this->select($sql, [$sid, $start->toDateTimeString(), $end->toDateTimeString()], 'observasi', $errors);
    }

    /**
     * @param  list<string>  $errors
     * @return list<object>
     */
    private function fetchOak(string $sid, CarbonImmutable $start, CarbonImmutable $end, array &$errors): array
    {
        $limit = self::PER_TYPE_LIMIT;
        $sql = "
            SELECT DISTINCT ON (o.id_oak)
                o.id_oak, o.tanggal_submit, o.aktivitas, o.sub_aktivitas, o.kesimpulan, o.tools_observasi,
                o.lokasi, o.detil_lokasi, o.latitude, o.longitude, o.url_foto,
                o.nama_pelapor, o.jabatan_fungsional_pelapor, o.perusahaan_pelapor,
                o.nama_team, o.jabatan_fungsional_team, o.peran_dalam_tim,
                k.\"PERUSAHAAN\" AS perusahaan_observee
            FROM bcbeats.mv_oak o
            LEFT JOIN bcbeats.m_karyawan_table k ON k.id = o.id_karyawan_team
            WHERE o.kode_sid_pelapor = ?
              AND o.tanggal_submit >= CAST(? AS timestamp)
              AND o.tanggal_submit < CAST(? AS timestamp)
            ORDER BY o.id_oak,
                     CASE WHEN UPPER(BTRIM(COALESCE(o.peran_dalam_tim, ''))) = 'OBSERVEE' THEN 0 ELSE 1 END,
                     o.tanggal_submit
            LIMIT {$limit}
        ";

        return $this->select($sql, [$sid, $start->toDateTimeString(), $end->toDateTimeString()], 'OAK', $errors);
    }

    /**
     * @param  list<mixed>  $bindings
     * @param  list<string>  $errors
     * @return list<object>
     */
    private function select(string $sql, array $bindings, string $source, array &$errors): array
    {
        try {
            return $this->olap->select($sql, $bindings, 4000);
        } catch (Throwable $e) {
            Log::warning('ControlRoom SAP duty '.$source.' gagal: '.$e->getMessage());
            $errors[] = 'Gagal memuat '.$source.'.';

            return [];
        }
    }

    /**
     * @param  array{sid: string, date: string, shift: string, window_start: string, window_end: string}  $meta
     * @param  list<array<string, mixed>>  $cards
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function payload(array $meta, array $cards, bool $reachable, array $errors = []): array
    {
        $counts = ['all' => count($cards), 'hazard' => 0, 'inspeksi' => 0, 'observasi' => 0, 'oak' => 0];
        foreach ($cards as $card) {
            $type = (string) $card['type'];
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        return [
            ...$meta,
            'reachable' => $reachable,
            'truncated' => $counts['hazard'] + $counts['inspeksi'] >= self::PER_TYPE_LIMIT
                || $counts['observasi'] >= self::PER_TYPE_LIMIT
                || $counts['oak'] >= self::PER_TYPE_LIMIT,
            'errors' => $errors,
            'counts' => $counts,
            'cards' => $cards,
        ];
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapHazardInspeksi(array $rows): array
    {
        $cards = [];
        foreach ($rows as $row) {
            $tools = $this->text($row->tools_observasi ?? null);
            if (! ControlRoomInspeksiHazardToolFilter::matches($tools === '—' ? '' : $tools)) {
                continue;
            }
            $jenis = strtoupper(trim((string) ($row->jenis_laporan ?? '')));
            $type = $jenis === 'INSPEKSI' ? 'inspeksi' : 'hazard';
            $at = $this->parseTime($row->tanggal_laporan ?? null);
            $headline = trim($jenis.($tools !== '—' ? ' - '.$tools : ''));

            $cards[] = $this->card(
                id: (string) ($row->id_laporan ?? ''),
                type: $type,
                typeLabel: $jenis !== '' ? $jenis : 'HAZARD',
                headline: $headline !== '' ? $headline : 'HAZARD',
                at: $at,
                subcategory: $this->text($row->subketidaksesuaian ?? $row->ketidaksesuaian ?? null),
                description: $this->text($row->deskripsi_temuan ?? null),
                pic: $this->text($row->nama_pic ?? null),
                picMeta: $this->roleCompany($row->jabatan_fungsional_pic ?? null, $row->perusahaan_pic ?? null),
                reporter: $this->text($row->nama_pelapor ?? null),
                reporterMeta: $this->roleCompany($row->jabatan_fungsional_pelapor ?? null, $row->perusahaan_pelapor ?? null),
                location: $this->text($row->lokasi ?? null),
                locationDetail: $this->text($row->detil_lokasi ?? null),
                status: $this->statusLabel($row->status_laporan ?? null),
                photoUrl: $this->photoUrl($row->url_foto ?? null),
                photoPageId: $this->photoPageId($row->url_foto ?? null, $row->id_laporan ?? null),
                latitude: $row->latitude ?? null,
                longitude: $row->longitude ?? null,
            );
        }

        return $cards;
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapObservasi(array $rows): array
    {
        $cards = [];
        foreach ($rows as $row) {
            $at = $this->parseTime($row->tanggal_observasi ?? null);
            $kegiatan = $this->text($row->jenis_kegiatan ?? $row->tools_observasi ?? null);

            $cards[] = $this->card(
                id: (string) ($row->id_observasi ?? ''),
                type: 'observasi',
                typeLabel: 'OBSERVASI',
                headline: $kegiatan !== '—' ? 'OBSERVASI - '.$kegiatan : 'OBSERVASI',
                at: $at,
                subcategory: $kegiatan,
                description: $this->text($row->catatan_observasi ?? null),
                pic: $this->text($row->nama_personil_diobservasi ?? null),
                picMeta: $this->roleCompany(
                    $row->jabatan_fungsional_personil_diobservasi ?? null,
                    $row->perusahaan_personil_diobservasi ?? null,
                ),
                reporter: $this->text($row->nama_pelapor ?? null),
                reporterMeta: $this->roleCompany($row->jabatan_fungsional_pelapor ?? null, $row->perusahaan_pelapor ?? null),
                location: $this->text($row->lokasi ?? null),
                locationDetail: $this->text($row->detil_lokasi ?? null),
                status: '—',
                photoUrl: $this->photoUrl($row->url_foto ?? null),
                photoPageId: null,
                latitude: $row->latitude ?? null,
                longitude: $row->longitude ?? null,
            );
        }

        return $cards;
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapOak(array $rows): array
    {
        $cardsById = [];
        foreach ($rows as $row) {
            $id = (string) ($row->id_oak ?? '');
            $isObservee = strtoupper(trim((string) ($row->peran_dalam_tim ?? ''))) === 'OBSERVEE';
            if ($id !== '' && isset($cardsById[$id]) && ! $isObservee) {
                continue;
            }

            $at = $this->parseTime($row->tanggal_submit ?? null);
            $aktivitas = $this->text($row->aktivitas ?? null);
            $key = $id !== '' ? $id : 'row-'.count($cardsById);

            $cardsById[$key] = $this->card(
                id: $id,
                type: 'oak',
                typeLabel: 'OAK',
                headline: $aktivitas !== '—' ? 'OAK - '.$aktivitas : 'OAK',
                at: $at,
                subcategory: $this->text($row->sub_aktivitas ?? null),
                description: $this->text($row->kesimpulan ?? null),
                pic: $this->text($row->nama_team ?? null),
                picMeta: $this->roleCompany(
                    $row->jabatan_fungsional_team ?? null,
                    $row->perusahaan_observee ?? null,
                ),
                reporter: $this->text($row->nama_pelapor ?? null),
                reporterMeta: $this->roleCompany($row->jabatan_fungsional_pelapor ?? null, $row->perusahaan_pelapor ?? null),
                location: $this->text($row->lokasi ?? null),
                locationDetail: $this->text($row->detil_lokasi ?? null),
                status: '—',
                photoUrl: $this->photoUrl($row->url_foto ?? null),
                photoPageId: null,
                latitude: $row->latitude ?? null,
                longitude: $row->longitude ?? null,
            );
        }

        return array_values($cardsById);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(
        string $id,
        string $type,
        string $typeLabel,
        string $headline,
        ?CarbonImmutable $at,
        string $subcategory,
        string $description,
        string $pic,
        string $picMeta,
        string $reporter,
        string $reporterMeta,
        string $location,
        string $locationDetail,
        string $status,
        ?string $photoUrl,
        ?string $photoPageId,
        mixed $latitude,
        mixed $longitude,
    ): array {
        return [
            'id' => $id !== '' ? $id : '—',
            'type' => $type,
            'type_label' => $typeLabel,
            'headline' => $headline,
            'submitted_at' => $at?->format('Y-m-d H:i:s') ?? '',
            'submitted_label' => $at?->format('Y-m-d H:i:s') ?? '—',
            'geotag' => $this->geotagLabel($at, $latitude, $longitude),
            'subcategory' => $subcategory,
            'description' => $description,
            'pic' => $pic,
            'pic_meta' => $picMeta,
            'reporter' => $reporter,
            'reporter_meta' => $reporterMeta,
            'location' => $location,
            'location_detail' => $locationDetail,
            'status' => $status,
            'photo_url' => $photoUrl,
            'photo_page_id' => $photoPageId,
        ];
    }

    private function parseTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }

    private function geotagLabel(?CarbonImmutable $at, mixed $latitude, mixed $longitude): ?string
    {
        $lat = trim((string) ($latitude ?? ''));
        $lng = trim((string) ($longitude ?? ''));
        if ($lat === '' || $lng === '' || $at === null) {
            return null;
        }

        return $at->format('H:i:s');
    }

    private function photoUrl(mixed $value): ?string
    {
        $url = trim((string) ($value ?? ''));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        if (preg_match('#/report/photoCar/#i', $url) === 1) {
            return null;
        }

        return $url;
    }

    private function photoPageId(mixed $url, mixed $reportId): ?string
    {
        $url = trim((string) ($url ?? ''));
        if (preg_match('#/report/photoCar/(\d+)#i', $url, $matches) === 1) {
            return $matches[1];
        }
        $id = trim((string) ($reportId ?? ''));

        return ctype_digit($id) ? $id : null;
    }

    private function text(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '—';
    }

    private function roleCompany(mixed $role, mixed $company): string
    {
        $parts = array_values(array_filter([
            trim((string) ($role ?? '')),
            trim((string) ($company ?? '')),
        ], fn (string $part): bool => $part !== ''));

        return $parts === [] ? '—' : implode(' — ', $parts);
    }

    private function statusLabel(mixed $value): string
    {
        $status = strtolower(trim((string) ($value ?? '')));
        if ($status === '') {
            return '—';
        }

        return match ($status) {
            'closed' => 'Closed',
            'open' => 'Open',
            default => ucfirst($status),
        };
    }
}
