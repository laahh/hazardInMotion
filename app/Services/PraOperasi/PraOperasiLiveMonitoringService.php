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
 * "Masih beroperasi" = checked_out_at kosong DAN belum melewati batas akhir
 * jendela shift-nya (lihat PraOperasiRosterShiftReader::operatingWindowEnd).
 * SENGAJA tidak lagi memakai jumlah jam tetap sejak checkin — banyak
 * karyawan tidak pernah tap checkout, jadi batas waktu harus mengikuti shift
 * (Shift 1 Siang 06-18, Shift 2 Malam 18-06 + toleransi lembur), bukan jarak
 * waktu generik dari satu checkin. Jawaban untuk pertanyaan terbuka #3 di
 * docs/pra-operasi-safety-framework.md.
 */
final class PraOperasiLiveMonitoringService
{
    public function __construct(
        private readonly PraOperasiCheckinReader $checkinReader,
        private readonly PraOperasiFatigueCheckReader $fatigueReader,
        private readonly PraOperasiDmsAlertReader $dmsAlertReader,
        private readonly PraOperasiPvtStatusReader $pvtReader,
        private readonly PraOperasiRosterShiftReader $rosterShiftReader,
        private readonly PraOperasiFitToContinueService $fitToContinue,
        private readonly PraOperasiPicNotifier $picNotifier,
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
            $tz = (string) config('app.timezone');

            $allCheckinAtBySid = array_combine(
                array_map(static fn (array $r): string => mb_strtoupper($r['kode_sid']), $checkins),
                array_map(static fn (array $r): string => $r['checked_in_at'], $checkins),
            );
            $shiftBySid = $this->rosterShiftReader->resolveForCheckins($allCheckinAtBySid, $date);

            $operating = array_values(array_filter(
                $checkins,
                function (array $r) use ($now, $tz, $shiftBySid): bool {
                    if (! empty($r['checked_out_at'])) {
                        return false;
                    }
                    $upper = mb_strtoupper($r['kode_sid']);
                    $shift = $shiftBySid[$upper]['shift'] ?? null;
                    try {
                        $checkinAt = Carbon::parse($r['checked_in_at'], $tz);
                    } catch (Throwable) {
                        return true;
                    }
                    if ($shift === null) {
                        return true;
                    }

                    return $now->lessThanOrEqualTo(PraOperasiRosterShiftReader::operatingWindowEnd($checkinAt, $shift));
                }
            ));

            if ($operating === []) {
                return array_merge($empty, ['up' => true]);
            }

            $sids = array_map(static fn (array $r): string => $r['kode_sid'], $operating);
            $fatigueBySid = $this->fatigueReader->statusForSidsOnDate($sids, $date);
            $alertBySid = $this->dmsAlertReader->dailyAlertBreakdownForSids($sids, $date);
            $tindakLanjutBySid = $this->lookupTindakLanjut($sids, $date);
            $checkinAtBySid = array_combine(
                array_map(static fn (array $r): string => mb_strtoupper($r['kode_sid']), $operating),
                array_map(static fn (array $r): string => $r['checked_in_at'], $operating),
            );
            $pvtBySid = $this->pvtReader->statusForCheckins($checkinAtBySid, $date);

            $cards = [];
            foreach ($operating as $row) {
                $upper = mb_strtoupper($row['kode_sid']);
                $fatigue = $fatigueBySid[$upper] ?? null;
                $alert = $alertBySid[$upper] ?? ['nyata' => 0, 'palsu' => 0, 'belum' => 0];
                $tier = $fatigue['tier'] ?? null;
                $tindakLanjut = $tindakLanjutBySid[$upper] ?? null;
                $pvt = $pvtBySid[$upper] ?? null;
                $shiftInfo = $shiftBySid[$upper] ?? null;
                $shiftCode = $shiftInfo['shift'] ?? null;

                $cards[] = [
                    'kode_sid' => $row['kode_sid'],
                    'nama' => $row['nama'] !== '' ? $row['nama'] : '-',
                    'perusahaan' => $row['perusahaan'] !== '' ? $row['perusahaan'] : 'Tidak diketahui',
                    'checked_in_at' => $row['checked_in_at'],
                    'shift' => $shiftCode,
                    'shift_label' => $shiftCode !== null ? PraOperasiFatigueCheckReader::shiftLabel($shiftCode) : null,
                    'shift_source' => $shiftInfo['source'] ?? null,
                    'roster_code' => $shiftInfo['roster_code'] ?? null,
                    'fatigue_tier' => $tier,
                    'fatigue_score' => $fatigue['kesiapan_score'] ?? null,
                    'pvt_status' => $pvt['status'] ?? 'belum',
                    'pvt_mean_rt_ms' => $pvt['mean_rt_ms'] ?? null,
                    'pvt_lapses' => $pvt['lapses'] ?? null,
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

                // Dalam grup status yang sama: paling urgent = tidak ada kontrol
                // sama sekali (belum Fatigue Test DAN belum PVT) digabung dengan
                // volume alert hari ini — inilah yang paling perlu segera dicek
                // langsung ke lapangan, bukan sekadar diurutkan abjad.
                $ua = self::lackOfControlScore($a);
                $ub = self::lackOfControlScore($b);
                if ($ua !== $ub) {
                    return $ub <=> $ua;
                }

                $alertA = $a['alert_nyata'] + $a['alert_belum'];
                $alertB = $b['alert_nyata'] + $b['alert_belum'];
                if ($alertA !== $alertB) {
                    return $alertB <=> $alertA;
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
     * 2 = belum Fatigue Test DAN belum PVT (tidak ada kontrol sama sekali hari
     * ini), 1 = salah satu belum, 0 = keduanya sudah dilakukan.
     */
    private static function lackOfControlScore(array $card): int
    {
        return ($card['fatigue_tier'] === null ? 1 : 0) + ($card['pvt_status'] === 'belum' ? 1 : 0);
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
     * Simpan catatan tindak lanjut baru untuk satu operator pada tanggal
     * tertentu, lalu kirim notifikasi WA ke PIC perusahaan operator tsb
     * (lihat PraOperasiPicNotifier) — kegagalan kirim WA tidak membatalkan
     * catatan yang sudah tersimpan.
     *
     * @return array{ok: bool, wa: array{attempted:int, sent:int, failed:int, recipients: list<array<string, mixed>>}}
     */
    public function catatTindakLanjut(
        string $kodeSid,
        string $date,
        ?string $statusSaatIni,
        ?string $catatan,
        ?int $userId,
        string $nama = '',
        string $perusahaan = '',
    ): array {
        $emptyWa = ['attempted' => 0, 'sent' => 0, 'failed' => 0, 'recipients' => []];

        try {
            PraOperasiTindakLanjut::create([
                'kode_sid' => $kodeSid,
                'tanggal' => $date,
                'status_saat_ditandai' => $statusSaatIni,
                'catatan' => $catatan,
                'user_id' => $userId,
            ]);
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'wa' => $emptyWa];
        }

        $wa = $emptyWa;
        if ($perusahaan !== '') {
            try {
                $wa = $this->picNotifier->notify(
                    $perusahaan,
                    $kodeSid,
                    $nama !== '' ? $nama : $kodeSid,
                    $statusSaatIni ?? '',
                    $catatan,
                    $date,
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        return ['ok' => true, 'wa' => $wa];
    }
}
