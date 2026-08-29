<?php

declare(strict_types=1);

namespace App\Actions\PeerPressure;

use App\Models\PeerPressureKejadianEdukasi;
use App\Models\ValidasiTbc;
use App\Services\PeerPressure\PeerPressureBerecordNitipService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ringkasan tiga kartu pada modal "Total Deviasi Pelanggaran":
 * BeRecord (uniq id · bcsid.bep_vw_berecord, filter golden_rules: terisi & bukan “Tidak Melanggar Golden Rules”),
 * Validasi TBC (tasklist terisi), Speak Up Fatigue (kategori deviasi pada kejadian).
 */
final class GetPeerPressureDeviationModalBreakdownAction
{
    private const MIN_YEAR = 2025;

    private const MAX_YEAR = 2026;

    public function __construct(
        private readonly PeerPressureBerecordNitipService $berecordNitip,
    ) {}

    /**
     * @return array{
     *   berecord_pspp_gr_total: int,
     *   validasi_tbc_tasklist_terisi_total: int,
     *   speak_up_fatigue_tidak_speak_total: int
     * }
     */
    public function __invoke(?int $year = null, ?int $month = null): array
    {
        $base = $this->scopedKejadianQuery($year, $month);

        $berecordTotal = $this->berecordNitip->countDistinctIdsGoldenRulesBaseline($year, $month);

        $tbcQuery = ValidasiTbc::query()
            ->whereRaw('LENGTH(TRIM(COALESCE(tasklist, \'\'))) > 0');

        if ($year !== null && $month !== null) {
            $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
            $m = max(1, min(12, $month));
            $start = Carbon::create($y, $m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $tbcQuery->where('created_at', '>=', $start)->where('created_at', '<=', $end);
        }

        $tbcTotal = $tbcQuery->count();

        $fatigueTotal = (clone $base)
            ->whereRaw('LOWER(TRIM(COALESCE(kategori_deviasi, \'\'))) = ?', ['tidak speak up fatigue'])
            ->count();

        return [
            'berecord_pspp_gr_total' => $berecordTotal,
            'validasi_tbc_tasklist_terisi_total' => $tbcTotal,
            'speak_up_fatigue_tidak_speak_total' => $fatigueTotal,
        ];
    }

    private function scopedKejadianQuery(?int $year, ?int $month): Builder
    {
        $q = PeerPressureKejadianEdukasi::query();
        if ($year !== null && $month !== null) {
            $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
            $m = max(1, min(12, $month));
            $start = Carbon::create($y, $m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $q->where('tanggal_temuan', '>=', $start->toDateString())
                ->where('tanggal_temuan', '<=', $end->toDateString());
        }

        return $q;
    }
}
