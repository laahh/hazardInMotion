<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Services\PraOperasi\PraOperasiDmsAlertReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Alert DMS per karyawan untuk /dms/roster-compliance-static — data roster
 * itu sendiri statis (snapshot), tapi alert DMS diambil LIVE dari
 * bcsid.mv_dms_alert/dms_alert (lihat PraOperasiDmsAlertReader) supaya tidak
 * ikut membekukan data personal yang terus berubah setiap hari.
 */
final class RosterComplianceAlertController extends Controller
{
    private const WINDOW_DAYS = 30;

    private const MAX_SIDS_PER_REQUEST = 200;

    public function __construct(
        private readonly PraOperasiDmsAlertReader $reader,
    ) {}

    /**
     * Jumlah alert 30 hari terakhir untuk sekumpulan SID (dipakai kolom
     * "Alert DMS" di tabel daftar karyawan — dipanggil per halaman tabel).
     */
    public function counts(Request $request): JsonResponse
    {
        $sids = $this->normalizeSids((array) $request->query('sids', []));
        $until = $this->normalizeDate((string) $request->query('until', ''));

        if ($sids === []) {
            return response()->json(['available' => $this->reader->isUp(), 'counts' => []]);
        }

        $since = Carbon::parse($until, config('app.timezone'))->subDays(self::WINDOW_DAYS - 1)->toDateString();

        return response()->json([
            'available' => $this->reader->isUp(),
            'counts' => $this->reader->fatigueAlertCountsForSids($sids, $since, $until),
        ]);
    }

    /**
     * Riwayat alert 30 hari terakhir untuk SATU SID (panel detail karyawan).
     */
    public function timeline(Request $request, string $sid): JsonResponse
    {
        $sid = mb_substr(trim($sid), 0, 20);
        if ($sid === '') {
            return response()->json(['message' => 'Kode SID tidak valid.'], 422);
        }

        $until = $this->normalizeDate((string) $request->query('until', ''));

        return response()->json([
            'available' => $this->reader->isUp(),
            'timeline' => $this->reader->alertTimelineForSid($sid, $until, self::WINDOW_DAYS, 50),
        ]);
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<string>
     */
    private function normalizeSids(array $raw): array
    {
        $sids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $s): string => mb_substr(trim((string) $s), 0, 20),
            $raw
        ), static fn (string $s): bool => $s !== '')));

        return array_slice($sids, 0, self::MAX_SIDS_PER_REQUEST);
    }

    private function normalizeDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return Carbon::now(config('app.timezone'))->toDateString();
        }

        return $date;
    }
}
