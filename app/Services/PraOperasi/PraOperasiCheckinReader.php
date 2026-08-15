<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Check-in RFID lolos hari ini untuk karyawan berjabatan "Operator".
 *
 * PENTING — kenapa ini TIDAK melakukan JOIN mv_checkinout_rfid ⋈ m_karyawan:
 * bcsid.m_karyawan berukuran ~6 GB untuk ±68 ribu baris (bloat) dan HANYA
 * punya index primary key (id) — tidak ada index di kode_sid maupun id_jabatan.
 * JOIN apa pun ke tabel ini (apalagi dengan UPPER(TRIM(...)) di kedua sisi)
 * berakhir sebagai sequential scan penuh, dan kalau dibungkus per-request
 * (apalagi berulang tiap ganti filter tanggal) inilah penyebab 504 Gateway
 * Timeout. Root cause sebenarnya butuh index dari sisi DB
 * (lihat catatan di PraOperasiOperatorRosterReader) — mitigasi di sisi
 * aplikasi: query m_karyawan hanya SEKALI (bukan join, bukan nested loop
 * per jabatan — pakai id_jabatan = ANY(array)) lalu di-cache lama sekali
 * (independen dari tanggal), baru daftar SID hasilnya dipakai ke
 * mv_checkinout_rfid yang sudah terindeks (kode_sid, tanggal_checkinout).
 */
final class PraOperasiCheckinReader
{
    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $rfidReader,
        private readonly PraOperasiOperatorRosterReader $rosterReader,
    ) {}

    public function isUp(): bool
    {
        return $this->rfidReader->isUp();
    }

    /**
     * Check-in (+ check-out bila ada) lolos pada tanggal (Asia/Makassar), untuk
     * seluruh operator di roster (di-cache lama, independen dari tanggal).
     * Pola sama dengan SportEvaluationPvtDashboardService (yang sudah terbukti
     * lancar di /evaluasi-well/pvt): ambil daftar SID dulu, baru WHERE-IN ke
     * mv_checkinout_rfid yang sudah terindeks — bukan JOIN.
     *
     * @return list<array{
     *     kode_sid: string, nama: string, jabatan: string, perusahaan: string,
     *     checked_in_at: string, checked_out_at: string|null, gate: string, status_lolos: string
     * }>
     */
    public function operatorCheckinsForDate(string $date): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $roster = $this->rosterReader->operatorRoster();
        if ($roster === []) {
            return [];
        }

        $cacheKey = 'pra_operasi:checkins:v3:'.$date;

        return Cache::remember($cacheKey, 30, function () use ($roster, $date): array {
            $sids = array_map(static fn (array $o): string => $o['kode_sid'], $roster);
            $checkins = $this->rfidReader->firstPassedCheckinsForSids($date, $sids);
            if ($checkins === []) {
                return [];
            }
            $checkouts = $this->rfidReader->lastPassedCheckoutsForSids($date, $sids);

            $list = [];
            foreach ($roster as $operator) {
                $upper = mb_strtoupper($operator['kode_sid']);
                $checkin = $checkins[$upper] ?? null;
                if ($checkin === null) {
                    continue;
                }
                $checkout = $checkouts[$upper] ?? null;

                $list[] = [
                    'kode_sid' => $operator['kode_sid'],
                    'nama' => $operator['nama'] !== '' ? $operator['nama'] : $checkin['nama_karyawan'],
                    'jabatan' => $operator['jabatan'],
                    'perusahaan' => $operator['perusahaan'] !== '' ? $operator['perusahaan'] : $checkin['perusahaan'],
                    'checked_in_at' => $checkin['checked_in_at'],
                    'checked_out_at' => $checkout['checked_in_at'] ?? null,
                    'gate' => $checkin['gate'],
                    'status_lolos' => $checkin['status_lolos'],
                ];
            }

            return $list;
        });
    }
}
