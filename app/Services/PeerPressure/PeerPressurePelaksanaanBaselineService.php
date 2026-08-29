<?php

declare(strict_types=1);

namespace App\Services\PeerPressure;

use App\Models\PeerPressureKejadianEdukasi;
use App\Models\ValidasiTbc;
use App\Support\PeerPressure\PeerPressureDashboardRestrictedContractors;
use App\Support\PeerPressure\PelaksanaanComplianceEvaluator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menghitung pelaksanaan peer pressure (sudah / belum) terhadap baseline tiga jalur,
 * dibatasi ke kontraktor {@see PeerPressureDashboardRestrictedContractors} dan dipecah per site.
 */
final class PeerPressurePelaksanaanBaselineService
{
    private const MIN_YEAR = 2025;

    private const MAX_YEAR = 2026;

    public const BLINDSPOT_KATEGORI = 'Blindspot To Be Concerned Hazards';

    public function __construct(
        private readonly PeerPressureBerecordNitipService $berecordNitip,
    ) {}

    /** @var array<string, array<string, mixed>> */
    private array $computeMemo = [];

    /**
     * @return array{
     *   baseline_total: int,
     *   selesai: int,
     *   belum: int,
     *   pct_selesai: float,
     *   pct_belum: float,
     *   baseline_be: int,
     *   selesai_be: int,
     *   baseline_tbc: int,
     *   selesai_tbc: int,
     *   baseline_fatigue: int,
     *   selesai_fatigue: int
     * }
     */
    public function compute(?int $year = null, ?int $month = null): array
    {
        $memoKey = ($year ?? 0).'-'.($month ?? 0);
        if (isset($this->computeMemo[$memoKey])) {
            return $this->computeMemo[$memoKey];
        }

        $bounds = $this->kejadianTanggalTemuanBounds($year, $month);

        $beCoMapFull = $this->berecordNitip->mapNormalizedBeRecordToCompany($year, $month);
        $beCoMap = [];
        foreach ($beCoMapFull as $k => $coRaw) {
            $kn = $this->normalizeKey((string) $k);
            if ($kn === '' || ! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($this->companyLabel($coRaw))) {
                continue;
            }
            $beCoMap[$kn] = $coRaw;
        }
        $beBaseline = count($beCoMap);
        $completedBe = $this->completedNormalizedIdBerecordSetRestricted($bounds);
        $selesaiBe = 0;
        foreach (array_keys($beCoMap) as $bk) {
            if (isset($completedBe[$bk])) {
                $selesaiBe++;
            }
        }

        $fatigueQ = $this->scopedKejadian($bounds)
            ->whereRaw('LOWER(TRIM(COALESCE(kategori_deviasi, \'\'))) = ?', ['tidak speak up fatigue']);
        $fatigueBaseline = 0;
        $selesaiFatigue = 0;
        foreach ($fatigueQ->cursor(['perusahaan', 'status_pelaksanaan_edukasi']) as $row) {
            if (! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($row->perusahaan ?? null)) {
                continue;
            }
            $fatigueBaseline++;
            if (PelaksanaanComplianceEvaluator::isPelaksanaanClosed($row->status_pelaksanaan_edukasi ?? null)) {
                $selesaiFatigue++;
            }
        }

        $tbcQuery = ValidasiTbc::query()
            ->whereRaw('LENGTH(TRIM(COALESCE(tasklist, \'\'))) > 0');
        if ($year !== null && $month !== null) {
            $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
            $m = max(1, min(12, $month));
            $start = Carbon::create($y, $m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $tbcQuery->where('created_at', '>=', $start)->where('created_at', '<=', $end);
        }

        $blindspotFull = $this->blindspotKejadianGroupsByTasklist($bounds);
        $tbcBaseline = 0;
        $selesaiTbc = 0;
        foreach ((clone $tbcQuery)->cursor(['tasklist']) as $row) {
            $tl = $this->normalizeKey((string) ($row->tasklist ?? ''));
            if ($tl === '') {
                continue;
            }
            $resolved = $this->resolveTbcRestricted($tl, $blindspotFull);
            if ($resolved === null) {
                continue;
            }
            $tbcBaseline++;
            if ($resolved['done']) {
                $selesaiTbc++;
            }
        }

        $baselineTotal = $beBaseline + $tbcBaseline + $fatigueBaseline;
        $selesai = $selesaiBe + $selesaiTbc + $selesaiFatigue;
        $selesai = min($selesai, $baselineTotal);
        $belum = max(0, $baselineTotal - $selesai);

        $pctSelesai = $baselineTotal > 0 ? round(100 * $selesai / $baselineTotal, 1) : 0.0;
        $pctBelum = $baselineTotal > 0 ? round(100 * $belum / $baselineTotal, 1) : 0.0;

        return $this->computeMemo[$memoKey] = [
            'baseline_total' => $baselineTotal,
            'selesai' => $selesai,
            'belum' => $belum,
            'pct_selesai' => $pctSelesai,
            'pct_belum' => $pctBelum,
            'baseline_be' => $beBaseline,
            'selesai_be' => $selesaiBe,
            'baseline_tbc' => $tbcBaseline,
            'selesai_tbc' => $selesaiTbc,
            'baseline_fatigue' => $fatigueBaseline,
            'selesai_fatigue' => $selesaiFatigue,
        ];
    }

    /**
     * Ringkasan per site dengan rincian perusahaan (hanya kontraktor terbatas); baris site bisa di-expand ke perusahaan.
     *
     * @return array{
     *   period_scope: string,
     *   period_label: string,
     *   baseline_scope: true,
     *   contractor_scope: list<string>,
     *   sites: list<array{
     *     site: string,
     *     site_key: string,
     *     total: int,
     *     selesai: int,
     *     pct: float,
     *     pct_belum: float,
     *     companies: list<array{company: string, total: int, selesai: int, pct: float, pct_belum: float}>
     *   }>
     * }
     */
    public function perusahaanHeatmap(?int $year = null, ?int $month = null): array
    {
        $bounds = $this->kejadianTanggalTemuanBounds($year, $month);

        /** @var array<string, array{companies: array<string, array{total: int, selesai: int}>}> $tree */
        $tree = [];

        $beCoMapFull = $this->berecordNitip->mapNormalizedBeRecordToCompany($year, $month);
        $completedBe = $this->completedNormalizedIdBerecordSetRestricted($bounds);
        $beSiteMap = $this->beRecordKeyToSiteFromKejadianRestricted($bounds);
        $companyPreferredSite = $this->companyPreferredSiteFromKejadian($bounds);
        $beKeyToSiteWp = $this->beRecordKeyToSiteFromWpKaryawan($year, $month);

        foreach ($beCoMapFull as $beKey => $coRaw) {
            $beNorm = $this->normalizeKey((string) $beKey);
            if ($beNorm === '' || ! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($this->companyLabel($coRaw))) {
                continue;
            }
            $co = $this->companyLabel($coRaw);
            $site = $this->resolveBeRecordSiteForAggregation($beSiteMap, $beNorm, $co, $companyPreferredSite, $beKeyToSiteWp);
            $done = isset($completedBe[$beNorm]);
            $this->accSiteCompany($tree, $site, $co, $done);
        }

        $fatigueQ = $this->scopedKejadian($bounds)
            ->whereRaw('LOWER(TRIM(COALESCE(kategori_deviasi, \'\'))) = ?', ['tidak speak up fatigue']);
        foreach ($fatigueQ->cursor(['perusahaan', 'status_pelaksanaan_edukasi', 'site']) as $row) {
            if (! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($row->perusahaan ?? null)) {
                continue;
            }
            $site = $this->siteLabel($row->site ?? null);
            $co = $this->companyLabel($row->perusahaan ?? null);
            $done = PelaksanaanComplianceEvaluator::isPelaksanaanClosed($row->status_pelaksanaan_edukasi ?? null);
            $this->accSiteCompany($tree, $site, $co, $done);
        }

        $tbcQuery = ValidasiTbc::query()
            ->whereRaw('LENGTH(TRIM(COALESCE(tasklist, \'\'))) > 0');
        if ($year !== null && $month !== null) {
            $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
            $m = max(1, min(12, $month));
            $start = Carbon::create($y, $m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $tbcQuery->where('created_at', '>=', $start)->where('created_at', '<=', $end);
        }

        $blindspotFull = $this->blindspotKejadianGroupsByTasklist($bounds);
        foreach ((clone $tbcQuery)->cursor(['tasklist']) as $row) {
            $tl = $this->normalizeKey((string) ($row->tasklist ?? ''));
            if ($tl === '') {
                continue;
            }
            $resolved = $this->resolveTbcRestricted($tl, $blindspotFull);
            if ($resolved === null) {
                continue;
            }
            $this->accSiteCompany($tree, $resolved['site'], $resolved['company'], $resolved['done']);
        }

        if ($year !== null && $month !== null) {
            $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
            $m = max(1, min(12, $month));
            $start = Carbon::create($y, $m, 1)->startOfDay();
            $periodLabel = sprintf('%s %d', $start->translatedFormat('F'), $y);

            return $this->finalizeSiteTreePayload($tree, 'month', $periodLabel);
        }

        return $this->finalizeSiteTreePayload($tree, 'all', 'Semua data (baseline · kontraktor terpilih)');
    }

    private const BASELINE_DETAIL_ROW_CAP = 300;

    /**
     * Daftar item baseline (BeRecord / TBC / fatigue) untuk sel tertentu pada ringkasan site × kontraktor.
     *
     * @return array{rows: list<array<string, mixed>>, truncated: bool, cap: int}
     */
    public function pelaksanaanBaselineDetailRows(
        string $scopeSite,
        ?string $scopeCompany,
        bool $terlaksana,
        ?int $year = null,
        ?int $month = null,
    ): array {
        $bounds = $this->kejadianTanggalTemuanBounds($year, $month);
        $rows = [];

        $beCoMapFull = $this->berecordNitip->mapNormalizedBeRecordToCompany($year, $month);
        $completedBe = $this->completedNormalizedIdBerecordSetRestricted($bounds);
        $beSiteMap = $this->beRecordKeyToSiteFromKejadianRestricted($bounds);
        $companyPreferredSite = $this->companyPreferredSiteFromKejadian($bounds);
        $beKeyToSiteWp = $this->beRecordKeyToSiteFromWpKaryawan($year, $month);
        $beSnap = $this->berecordDisplayByNormalizedKey($bounds);

        foreach ($beCoMapFull as $beKey => $coRaw) {
            $beNorm = $this->normalizeKey((string) $beKey);
            if ($beNorm === '' || ! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($this->companyLabel($coRaw))) {
                continue;
            }
            $co = $this->companyLabel($coRaw);
            $site = $this->resolveBeRecordSiteForAggregation($beSiteMap, $beNorm, $co, $companyPreferredSite, $beKeyToSiteWp);
            $done = isset($completedBe[$beNorm]);
            if ($done !== $terlaksana || ! $this->baselineDetailMatchesScope($site, $co, $scopeSite, $scopeCompany)) {
                continue;
            }
            $snap = $beSnap[$beNorm] ?? null;
            $rows[] = [
                'jenis' => 'berecord',
                'jenis_label' => 'BeRecord',
                'referensi' => $snap['id_berecord'] ?? $beNorm,
                'perusahaan' => $co,
                'site' => $site,
                'tanggal_temuan' => $this->formatDateOptional($snap['tanggal_temuan'] ?? null),
                'status_pelaksanaan_edukasi' => $snap['status_pelaksanaan_edukasi'] ?? null,
                'sudah_peer_pressure' => $done,
                'kejadian_edukasi_id' => isset($snap['kejadian_edukasi_id']) ? (int) $snap['kejadian_edukasi_id'] : null,
            ];
        }

        $fatigueQ = $this->scopedKejadian($bounds)
            ->whereRaw('LOWER(TRIM(COALESCE(kategori_deviasi, \'\'))) = ?', ['tidak speak up fatigue']);
        foreach ($fatigueQ->cursor(['id', 'perusahaan', 'status_pelaksanaan_edukasi', 'site', 'tanggal_temuan']) as $row) {
            if (! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($row->perusahaan ?? null)) {
                continue;
            }
            $site = $this->siteLabel($row->site ?? null);
            $co = $this->companyLabel($row->perusahaan ?? null);
            $done = PelaksanaanComplianceEvaluator::isPelaksanaanClosed($row->status_pelaksanaan_edukasi ?? null);
            if ($done !== $terlaksana || ! $this->baselineDetailMatchesScope($site, $co, $scopeSite, $scopeCompany)) {
                continue;
            }
            $rows[] = [
                'jenis' => 'fatigue',
                'jenis_label' => 'Fatigue (tidak speak up)',
                'referensi' => 'Kejadian #'.(string) ($row->id ?? ''),
                'perusahaan' => $co,
                'site' => $site,
                'tanggal_temuan' => $this->formatDateOptional($row->tanggal_temuan ?? null),
                'status_pelaksanaan_edukasi' => $row->status_pelaksanaan_edukasi ?? null,
                'sudah_peer_pressure' => $done,
                'kejadian_edukasi_id' => isset($row->id) ? (int) $row->id : null,
            ];
        }

        $tbcQuery = ValidasiTbc::query()
            ->whereRaw('LENGTH(TRIM(COALESCE(tasklist, \'\'))) > 0');
        if ($year !== null && $month !== null) {
            $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
            $m = max(1, min(12, $month));
            $start = Carbon::create($y, $m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $tbcQuery->where('created_at', '>=', $start)->where('created_at', '<=', $end);
        }

        $blindspotFull = $this->blindspotKejadianGroupsByTasklist($bounds);
        foreach ((clone $tbcQuery)->cursor(['id', 'tasklist', 'created_at']) as $row) {
            $tl = $this->normalizeKey((string) ($row->tasklist ?? ''));
            if ($tl === '') {
                continue;
            }
            $resolved = $this->resolveTbcRestricted($tl, $blindspotFull);
            if ($resolved === null) {
                continue;
            }
            $done = $resolved['done'];
            if ($done !== $terlaksana || ! $this->baselineDetailMatchesScope($resolved['site'], $resolved['company'], $scopeSite, $scopeCompany)) {
                continue;
            }
            $taskRaw = trim((string) ($row->tasklist ?? ''));
            $rows[] = [
                'jenis' => 'tbc',
                'jenis_label' => 'Validasi TBC (tasklist)',
                'referensi' => $taskRaw !== '' ? $taskRaw : $tl,
                'perusahaan' => $resolved['company'],
                'site' => $resolved['site'],
                'tanggal_temuan' => $this->formatDateOptional($row->created_at ?? null),
                'status_pelaksanaan_edukasi' => $done ? 'CLOSED/SELESAI (melalui blindspot)' : null,
                'sudah_peer_pressure' => $done,
                'validasi_tbc_id' => isset($row->id) ? (int) $row->id : null,
                'kejadian_edukasi_id' => null,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $ja = (string) ($a['jenis'] ?? '');
            $jb = (string) ($b['jenis'] ?? '');
            if ($ja !== $jb) {
                return $ja <=> $jb;
            }

            return strcmp((string) ($a['referensi'] ?? ''), (string) ($b['referensi'] ?? ''));
        });

        $truncated = count($rows) > self::BASELINE_DETAIL_ROW_CAP;
        if ($truncated) {
            $rows = array_slice($rows, 0, self::BASELINE_DETAIL_ROW_CAP);
        }

        return [
            'rows' => $rows,
            'truncated' => $truncated,
            'cap' => self::BASELINE_DETAIL_ROW_CAP,
        ];
    }

    private function baselineDetailMatchesScope(string $site, string $company, string $scopeSite, ?string $scopeCompany): bool
    {
        if ($site !== $scopeSite) {
            return false;
        }
        if ($scopeCompany !== null && $scopeCompany !== '') {
            return $company === $scopeCompany;
        }

        return true;
    }

    /**
     * @param  array{0: ?string, 1: ?string}  $tanggalBounds
     * @return array<string, array{id_berecord: string, tanggal_temuan: mixed, status_pelaksanaan_edukasi: mixed, kejadian_edukasi_id: int}>
     */
    private function berecordDisplayByNormalizedKey(array $tanggalBounds): array
    {
        $q = $this->scopedKejadian($tanggalBounds)
            ->whereRaw('LENGTH(TRIM(COALESCE(id_berecord, \'\'))) > 0');

        $out = [];
        foreach ($q->cursor(['id', 'id_berecord', 'tanggal_temuan', 'status_pelaksanaan_edukasi', 'perusahaan']) as $row) {
            if (! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($row->perusahaan ?? null)) {
                continue;
            }
            $nk = $this->normalizeKey((string) ($row->id_berecord ?? ''));
            if ($nk === '') {
                continue;
            }
            if (! isset($out[$nk])) {
                $out[$nk] = [
                    'id_berecord' => trim((string) ($row->id_berecord)),
                    'tanggal_temuan' => $row->tanggal_temuan ?? null,
                    'status_pelaksanaan_edukasi' => $row->status_pelaksanaan_edukasi ?? null,
                    'kejadian_edukasi_id' => (int) $row->id,
                ];
            }
        }

        return $out;
    }

    private function formatDateOptional(mixed $d): ?string
    {
        if ($d === null) {
            return null;
        }
        if ($d instanceof \DateTimeInterface) {
            return Carbon::instance($d)->toDateString();
        }
        $s = trim((string) $d);

        return $s === '' ? null : $s;
    }

    /**
     * @param  array<string, array{companies: array<string, array{total: int, selesai: int}>}>  $tree
     * @return array{period_scope: string, period_label: string, baseline_scope: true, contractor_scope: list<string>, sites: list<array<string, mixed>>}
     */
    private function finalizeSiteTreePayload(array $tree, string $periodScope, string $periodLabel): array
    {
        $sites = [];
        foreach ($tree as $siteName => $data) {
            $companies = [];
            $tTotal = 0;
            $tSelesai = 0;
            foreach ($data['companies'] as $cn => $cell) {
                $tTotal += $cell['total'];
                $tSelesai += $cell['selesai'];
                $belum = $cell['total'] - $cell['selesai'];
                $companies[] = [
                    'company' => $cn,
                    'total' => $cell['total'],
                    'selesai' => $cell['selesai'],
                    'pct' => $cell['total'] > 0 ? round(100 * $cell['selesai'] / $cell['total'], 1) : 0.0,
                    'pct_belum' => $cell['total'] > 0 ? round(100 * $belum / $cell['total'], 1) : 0.0,
                ];
            }
            usort($companies, fn (array $a, array $b): int => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));

            $belumSite = $tTotal - $tSelesai;
            $sites[] = [
                'site' => $siteName,
                'site_key' => base64_encode($siteName),
                'total' => $tTotal,
                'selesai' => $tSelesai,
                'pct' => $tTotal > 0 ? round(100 * $tSelesai / $tTotal, 1) : 0.0,
                'pct_belum' => $tTotal > 0 ? round(100 * $belumSite / $tTotal, 1) : 0.0,
                'companies' => $companies,
            ];
        }

        usort($sites, fn (array $a, array $b): int => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));

        return [
            'period_scope' => $periodScope,
            'period_label' => $periodLabel,
            'baseline_scope' => true,
            'contractor_scope' => PeerPressureDashboardRestrictedContractors::COMPANIES,
            'sites' => $sites,
        ];
    }

    /**
     * @param  array<string, array{companies: array<string, array{total: int, selesai: int}>}>  $tree
     */
    private function accSiteCompany(array &$tree, string $site, string $company, bool $selesai): void
    {
        if (! isset($tree[$site])) {
            $tree[$site] = ['companies' => []];
        }
        if (! isset($tree[$site]['companies'][$company])) {
            $tree[$site]['companies'][$company] = ['total' => 0, 'selesai' => 0];
        }
        $tree[$site]['companies'][$company]['total']++;
        if ($selesai) {
            $tree[$site]['companies'][$company]['selesai']++;
        }
    }

    private function companyLabel(mixed $perusahaan): string
    {
        $p = trim((string) $perusahaan);

        return $p === '' ? '(Tidak diisi)' : $p;
    }

    private function siteLabel(mixed $site): string
    {
        $s = trim((string) $site);

        return $s === '' ? '(Site tidak diisi)' : $s;
    }

    /**
     * Site yang paling sering muncul per perusahaan (kontraktor terbatas) pada kejadian dalam periode,
     * hanya dari baris dengan kolom site terisi — dipakai fallback setelah site dari kejadian dan site dari bep_vw_wp_karyawan (kode_sid).
     *
     * @param  array{0: ?string, 1: ?string}  $tanggalBounds
     * @return array<string, string> label perusahaan => label site
     */
    private function companyPreferredSiteFromKejadian(array $tanggalBounds): array
    {
        /** @var array<string, array<string, int>> $counts */
        $counts = [];
        foreach ($this->scopedKejadian($tanggalBounds)->cursor(['perusahaan', 'site']) as $row) {
            if (! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($row->perusahaan ?? null)) {
                continue;
            }
            $site = $this->siteLabel($row->site ?? null);
            if ($site === '(Site tidak diisi)') {
                continue;
            }
            $co = $this->companyLabel($row->perusahaan ?? null);
            if (! isset($counts[$co])) {
                $counts[$co] = [];
            }
            $counts[$co][$site] = ($counts[$co][$site] ?? 0) + 1;
        }

        $out = [];
        foreach ($counts as $co => $bySite) {
            if ($bySite === []) {
                continue;
            }
            $bestN = max($bySite);
            $candidates = array_keys(array_filter($bySite, static fn (int $n): bool => $n === $bestN));
            sort($candidates, SORT_STRING);

            $out[$co] = $candidates[0];
        }

        return $out;
    }

    /**
     * Site per kunci BeRecord dari OLAP: kode_sid di bcsid.bep_vw_berecord → site di bep_vw_wp_karyawan (baris terbaru per sid).
     *
     * @return array<string, string> normalized BeRecord key => site (hanya jika site WP terisi)
     */
    private function beRecordKeyToSiteFromWpKaryawan(?int $year, ?int $month): array
    {
        $beToSid = $this->berecordNitip->mapNormalizedBeRecordToKodeSid($year, $month);
        $lowerSids = [];
        foreach ($beToSid as $sidRaw) {
            $s = strtolower(trim((string) $sidRaw));
            if ($s !== '') {
                $lowerSids[$s] = true;
            }
        }
        if ($lowerSids === []) {
            return [];
        }

        $sidToSite = $this->berecordNitip->mapKodeSidLowerToSiteFromWpKaryawan(array_keys($lowerSids));
        $out = [];
        foreach ($beToSid as $beK => $sidRaw) {
            $beNorm = $this->normalizeKey((string) $beK);
            if ($beNorm === '') {
                continue;
            }
            $s = strtolower(trim((string) $sidRaw));
            if ($s === '') {
                continue;
            }
            $site = trim((string) ($sidToSite[$s] ?? ''));
            if ($site !== '') {
                $out[$beNorm] = $site;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $beSiteMap  normalized BeRecord key => site dari kejadian
     * @param  array<string, string>  $companyPreferredSite  perusahaan => site dominan
     * @param  array<string, string>  $beKeyToSiteFromWp  normalized BeRecord key => site dari bep_vw_wp_karyawan
     */
    private function resolveBeRecordSiteForAggregation(
        array $beSiteMap,
        string $beKey,
        string $company,
        array $companyPreferredSite,
        array $beKeyToSiteFromWp = [],
    ): string {
        $fromId = $beSiteMap[$beKey] ?? null;
        if ($fromId !== null && $fromId !== '(Site tidak diisi)') {
            return $fromId;
        }

        $fromWp = trim((string) ($beKeyToSiteFromWp[$beKey] ?? ''));
        if ($fromWp !== '') {
            return $fromWp;
        }

        return $companyPreferredSite[$company] ?? '(Site tidak diketahui)';
    }

    /**
     * @param  array<string, list<array{co: string, closed: bool, site: string}>>  $fullGroups
     * @return array{done: bool, company: string, site: string}|null
     */
    private function resolveTbcRestricted(string $tl, array $fullGroups): ?array
    {
        $entries = $fullGroups[$tl] ?? [];
        $allowed = [];
        foreach ($entries as $e) {
            if (PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($e['co'])) {
                $allowed[] = $e;
            }
        }
        if ($allowed === []) {
            return null;
        }

        $done = false;
        $company = $allowed[0]['co'];
        $site = $allowed[0]['site'];
        foreach ($allowed as $e) {
            if ($e['closed']) {
                $done = true;
                $company = $e['co'];
                $site = $e['site'];

                break;
            }
        }

        return ['done' => $done, 'company' => $company, 'site' => $site];
    }

    /**
     * @param  array{0: ?string, 1: ?string}  $tanggalBounds
     * @return array<string, true>
     */
    private function completedNormalizedIdBerecordSetRestricted(array $tanggalBounds): array
    {
        $q = $this->scopedKejadian($tanggalBounds)
            ->where(function ($q): void {
                $this->applyPelaksanaanSelesaiScope($q);
            })
            ->whereRaw('LENGTH(TRIM(COALESCE(id_berecord, \'\'))) > 0');

        $keys = [];
        foreach ($q->cursor(['id_berecord', 'perusahaan']) as $row) {
            if (! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($row->perusahaan ?? null)) {
                continue;
            }
            $k = $this->normalizeKey((string) ($row->id_berecord ?? ''));
            if ($k !== '') {
                $keys[$k] = true;
            }
        }

        return $keys;
    }

    /**
     * @param  array{0: ?string, 1: ?string}  $tanggalBounds
     * @return array<string, string> normalized id_berecord => site label
     */
    private function beRecordKeyToSiteFromKejadianRestricted(array $tanggalBounds): array
    {
        $q = $this->scopedKejadian($tanggalBounds)
            ->whereRaw('LENGTH(TRIM(COALESCE(id_berecord, \'\'))) > 0');

        $out = [];
        foreach ($q->cursor(['id_berecord', 'site', 'perusahaan']) as $row) {
            if (! PeerPressureDashboardRestrictedContractors::isAllowedCompanyLabel($row->perusahaan ?? null)) {
                continue;
            }
            $k = $this->normalizeKey((string) ($row->id_berecord ?? ''));
            if ($k === '') {
                continue;
            }
            if (! isset($out[$k])) {
                $out[$k] = $this->siteLabel($row->site ?? null);
            }
        }

        return $out;
    }

    /**
     * @param  array{0: ?string, 1: ?string}  $tanggalBounds
     * @return array<string, list<array{co: string, closed: bool, site: string}>>
     */
    private function blindspotKejadianGroupsByTasklist(array $tanggalBounds): array
    {
        $rows = $this->scopedKejadian($tanggalBounds)
            ->whereRaw('LOWER(TRIM(COALESCE(kategori_deviasi, \'\'))) = ?', [strtolower(trim(self::BLINDSPOT_KATEGORI))])
            ->whereRaw('LENGTH(TRIM(COALESCE(tasklist_temuan, \'\'))) > 0')
            ->get(['perusahaan', 'status_pelaksanaan_edukasi', 'tasklist_temuan', 'site']);

        /** @var array<string, list<array{co: string, closed: bool, site: string}>> $groups */
        $groups = [];
        foreach ($rows as $k) {
            $tl = $this->normalizeKey((string) ($k->tasklist_temuan ?? ''));
            if ($tl === '') {
                continue;
            }
            $groups[$tl][] = [
                'co' => $this->companyLabel($k->perusahaan ?? null),
                'closed' => PelaksanaanComplianceEvaluator::isPelaksanaanClosed($k->status_pelaksanaan_edukasi ?? null),
                'site' => $this->siteLabel($k->site ?? null),
            ];
        }

        return $groups;
    }

    /**
     * @return array{0: ?string, 1: ?string} [startDate, endDate] Y-m-d or [null, null]
     */
    private function kejadianTanggalTemuanBounds(?int $year, ?int $month): array
    {
        if ($year === null || $month === null) {
            return [null, null];
        }
        $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
        $m = max(1, min(12, $month));
        $start = Carbon::create($y, $m, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }

    /**
     * @param  array{0: ?string, 1: ?string}  $tanggalBounds
     */
    private function scopedKejadian(array $tanggalBounds): Builder
    {
        $q = PeerPressureKejadianEdukasi::query();
        [$startS, $endS] = $tanggalBounds;
        if ($startS !== null && $endS !== null) {
            $q->where('tanggal_temuan', '>=', $startS)
                ->where('tanggal_temuan', '<=', $endS);
        }

        return $q;
    }

    private function applyPelaksanaanSelesaiScope(Builder $q): void
    {
        $q->whereRaw('UPPER(TRIM(COALESCE(status_pelaksanaan_edukasi, \'\'))) LIKE ?', ['%CLOSE%'])
            ->orWhereRaw('UPPER(TRIM(COALESCE(status_pelaksanaan_edukasi, \'\'))) LIKE ?', ['%SELESAI%']);
    }

    private function normalizeKey(string $s): string
    {
        return strtolower(trim($s));
    }
}
