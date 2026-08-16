<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Models\PraOperasi\PraOperasiTindakLanjut;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Fase 2 (Saat Operasi) — orkestrator dashboard live monitoring. Hanya
 * memproses operator yang MASIH beroperasi hari itu, gabungan hasil Fatigue
 * Test pagi + kondisi alert DMS hari itu, dinilai lewat PraOperasiFitToContinueService.
 *
 * "Masih beroperasi" = checked_out_at kosong DAN belum melewati
 * MAX_OPERATING_HOURS sejak checkin (shift standar tambang 12 jam; batas 16
 * jam memberi ruang lembur wajar tanpa salah anggap orang yang lupa tap
 * keluar 20+ jam lalu sebagai "masih beroperasi"). Jawaban untuk pertanyaan
 * terbuka #3 di docs/pra-operasi-safety-framework.md.
 */
final class PraOperasiLiveMonitoringService
{
    private const MAX_OPERATING_HOURS = 16;

    public function __construct(
        private readonly PraOperasiCheckinReader $checkinReader,
        private readonly PraOperasiFatigueCheckReader $fatigueReader,
        private readonly PraOperasiDmsAlertReader $dmsAlertReader,
        private readonly PraOperasiFitToContinueService $fitToContinue,
    ) {}

    public function isUp(): bool
    {
        return $this->checkinReader->isUp();
    }

    /**
     * @return array{
     *     up: bool, dateLabel: string, lastUpdated: string,
     *     redFlags: list<array<string, mixed>>,
     *     cards: list<array<string, mixed>>,
     *     alertFeed: list<array{kode_sid:string, nama:string, waktu:string, name:string, status:string}>,
     *     kpi: array{beroperasi:int, fit:int, perlu_perhatian:int, tarik:int, red_flag:int}
     * }
     */
    public function snapshot(string $date): array
    {
        $empty = [
            'up' => false,
            'date' => $date,
            'dateLabel' => $this->dateLabel($date),
            'lastUpdated' => Carbon::now(config('app.timezone'))->format('H:i:s'),
            'redFlags' => [],
            'cards' => [],
            'alertFeed' => [],
            'kpi' => ['beroperasi' => 0, 'fit' => 0, 'perlu_perhatian' => 0, 'tarik' => 0, 'red_flag' => 0],
        ];

        if (! $this->isUp()) {
            return $empty;
        }

        try {
            $checkins = $this->checkinReader->operatorCheckinsForDate($date);
            $now = Carbon::now(config('app.timezone'));
            $operating = array_values(array_filter(
                $checkins,
                function (array $r) use ($now): bool {
                    if (! empty($r['checked_out_at'])) {
                        return false;
                    }
                    try {
                        $hoursSinceCheckin = ($now->getTimestamp() - Carbon::parse($r['checked_in_at'])->getTimestamp()) / 3600;
                    } catch (Throwable) {
                        return true;
                    }

                    return $hoursSinceCheckin <= self::MAX_OPERATING_HOURS;
                }
            ));

            if ($operating === []) {
                return array_merge($empty, ['up' => true]);
            }

            $sids = array_map(static fn (array $r): string => $r['kode_sid'], $operating);
            $fatigueBySid = $this->fatigueReader->statusForSidsOnDate($sids, $date);
            $alertBySid = $this->dmsAlertReader->dailyAlertBreakdownForSids($sids, $date);
            $tindakLanjutBySid = $this->lookupTindakLanjut($sids, $date);

            $cards = [];
            foreach ($operating as $row) {
                $upper = mb_strtoupper($row['kode_sid']);
                $fatigue = $fatigueBySid[$upper] ?? null;
                $alert = $alertBySid[$upper] ?? ['nyata' => 0, 'palsu' => 0, 'belum' => 0];
                $tier = $fatigue['tier'] ?? null;
                $tindakLanjut = $tindakLanjutBySid[$upper] ?? null;

                $cards[] = [
                    'kode_sid' => $row['kode_sid'],
                    'nama' => $row['nama'] !== '' ? $row['nama'] : '-',
                    'perusahaan' => $row['perusahaan'] !== '' ? $row['perusahaan'] : 'Tidak diketahui',
                    'checked_in_at' => $row['checked_in_at'],
                    'fatigue_tier' => $tier,
                    'fatigue_score' => $fatigue['kesiapan_score'] ?? null,
                    'alert_nyata' => $alert['nyata'],
                    'alert_palsu' => $alert['palsu'],
                    'alert_belum' => $alert['belum'],
                    'status' => $this->fitToContinue->status($tier, $alert),
                    'is_red_flag' => $this->fitToContinue->isRedFlagProcess($tier),
                    'sudah_ditindaklanjuti' => $tindakLanjut !== null,
                    'catatan_tindak_lanjut' => $tindakLanjut,
                ];
            }

            usort($cards, static function (array $a, array $b): int {
                $rank = ['tarik' => 0, 'perlu_perhatian' => 1, 'fit_pantau' => 2, 'fit' => 3];
                $ra = $rank[$a['status']] ?? 1;
                $rb = $rank[$b['status']] ?? 1;
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }

                return strcmp((string) $a['nama'], (string) $b['nama']);
            });

            $redFlags = array_values(array_filter($cards, static fn (array $c): bool => $c['is_red_flag']));

            $kpi = ['beroperasi' => count($cards), 'fit' => 0, 'perlu_perhatian' => 0, 'tarik' => 0, 'red_flag' => count($redFlags)];
            foreach ($cards as $c) {
                match ($c['status']) {
                    'fit', 'fit_pantau' => $kpi['fit']++,
                    'perlu_perhatian' => $kpi['perlu_perhatian']++,
                    'tarik' => $kpi['tarik']++,
                    default => null,
                };
            }

            return [
                'up' => true,
                'date' => $date,
                'dateLabel' => $this->dateLabel($date),
                'lastUpdated' => Carbon::now(config('app.timezone'))->format('H:i:s'),
                'redFlags' => $redFlags,
                'cards' => $cards,
                'alertFeed' => $this->dmsAlertReader->recentAlertsForSids($sids, $date, 25),
                'kpi' => $kpi,
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    private function dateLabel(string $date): string
    {
        try {
            return Carbon::parse($date, config('app.timezone'))->translatedFormat('d M Y');
        } catch (Throwable) {
            return $date;
        }
    }

    /**
     * @param  list<string>  $sids
     * @return array<string, array{catatan:string|null, status_saat_ditandai:string|null, ditandai_pada:string}>
     */
    private function lookupTindakLanjut(array $sids, string $date): array
    {
        try {
            $upperSids = array_map(static fn (string $s): string => mb_strtoupper($s), $sids);
            $out = [];
            PraOperasiTindakLanjut::query()
                ->whereDate('tanggal', $date)
                ->whereIn(\Illuminate\Support\Facades\DB::raw('UPPER(kode_sid)'), $upperSids)
                ->orderByDesc('created_at')
                ->get()
                ->each(function ($row) use (&$out): void {
                    $key = mb_strtoupper($row->kode_sid);
                    if (! isset($out[$key])) {
                        $out[$key] = [
                            'catatan' => $row->catatan,
                            'status_saat_ditandai' => $row->status_saat_ditandai,
                            'ditandai_pada' => $row->created_at->format('H:i'),
                        ];
                    }
                });

            return $out;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Simpan catatan tindak lanjut baru untuk satu operator pada tanggal tertentu.
     */
    public function catatTindakLanjut(string $kodeSid, string $date, ?string $statusSaatIni, ?string $catatan, ?int $userId): bool
    {
        try {
            PraOperasiTindakLanjut::create([
                'kode_sid' => $kodeSid,
                'tanggal' => $date,
                'status_saat_ditandai' => $statusSaatIni,
                'catatan' => $catatan,
                'user_id' => $userId,
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
