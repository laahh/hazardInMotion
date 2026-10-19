<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\SidRosterBannedMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Memasukkan "Pelanggaran periode kerja > 70 hari" (daftar onsite dari
 * DmsRosterRegLiveService) ke sid_roster_banned_master — menu Master Roster
 * Banned di /pra-operasi/roster-banned.
 *
 * Isi baris mengikuti baris yang sudah ada di master: nik, sid, nama,
 * perusahaan = kode PT (BUMA, BAR, …), site_dedicated = site, alasan baku,
 * tanggal_pelanggaran = hari pertama melewati 70 hari (hari ke-71).
 *
 * Penanganan duplikat: orang yang SID ATAU NIK-nya masih punya kasus banned
 * AKTIF (belum di-unban, lihat DmsRosterBannedAktif) dilewati — sama dengan
 * pengecualian di kartu aturan. Kasus yang sudah di-unban tidak menghalangi:
 * pelanggaran baru mendapat baris master baru. Pemeriksaan diulang di dalam
 * transaksi sesaat sebelum insert, jadi dua proses yang berjalan bersamaan
 * tidak menggandakan baris.
 */
final class DmsRosterAutoBannedService
{
    /**
     * Calon baris master dari daftar onsite, sudah dibuang yang tidak punya
     * NIK (wajib di master) atau sudah tercatat.
     *
     * @param  list<array<string, mixed>>  $onsite  $hasil['daftar']['onsite'] dari DmsRosterRegLiveService.
     * @return array{calon: list<array<string, mixed>>, dilewati: list<array{sid:string,nama:string,alasan:string}>}
     */
    public function calon(array $onsite): array
    {
        $sids = array_values(array_unique(array_map(static fn (array $r): string => (string) $r['sid'], $onsite)));
        if ($sids === []) {
            return ['calon' => [], 'dilewati' => []];
        }

        $nik = DB::table('dms_roster_karyawan')->whereIn('kode_sid', $sids)->pluck('nik', 'kode_sid')->all();
        [$sidAda, $nikAda] = $this->sudahTercatat($sids, array_values(array_filter(array_map('strval', $nik))));

        $calon = [];
        $dilewati = [];
        foreach ($onsite as $r) {
            $sid = (string) $r['sid'];
            $n = trim((string) ($nik[$sid] ?? ''));

            $alasan = match (true) {
                isset($sidAda[$sid]) => 'SID masih punya kasus banned aktif (belum di-unban)',
                $n === '' => 'NIK kosong di master karyawan',
                isset($nikAda[$n]) => 'NIK masih punya kasus banned aktif (belum di-unban)',
                default => null,
            };
            if ($alasan !== null) {
                $dilewati[] = ['sid' => $sid, 'nama' => (string) $r['nama'], 'alasan' => $alasan];

                continue;
            }

            $calon[] = [
                'nik' => $n,
                'sid' => $sid,
                'nama' => (string) $r['nama'],
                'perusahaan' => (string) $r['pt'] !== '' ? (string) $r['pt'] : null,
                'site_dedicated' => (string) $r['site'] !== '—' ? (string) $r['site'] : null,
                'alasan_pelanggaran' => (string) config('dms_roster.reg_live.auto_banned.alasan', 'BAN SID - Kepatuhan Roster Karyawan'),
                'tanggal_pelanggaran' => $r['tgl_pelanggaran_iso'] ?? null,
                'created_by' => (string) config('dms_roster.reg_live.auto_banned.created_by', 'Sistem (RFID otomatis)'),
                // Hanya untuk ditampilkan saat pratinjau; tidak disimpan.
                '_periode' => (string) $r['nilai'],
            ];
            // Satu orang cukup sekali walau muncul ganda di daftar.
            $sidAda[$sid] = true;
            $nikAda[$n] = true;
        }

        return ['calon' => $calon, 'dilewati' => $dilewati];
    }

    /**
     * Simpan calon ke master. Mengembalikan jumlah baris yang benar-benar
     * ditambahkan.
     *
     * @param  list<array<string, mixed>>  $calon  Hasil calon()['calon'].
     */
    public function masukkan(array $calon): int
    {
        if ($calon === []) {
            return 0;
        }

        return DB::transaction(function () use ($calon): int {
            [$sidAda, $nikAda] = $this->sudahTercatat(array_column($calon, 'sid'), array_column($calon, 'nik'));
            $ditambah = 0;

            foreach ($calon as $c) {
                if (isset($sidAda[$c['sid']]) || isset($nikAda[$c['nik']])) {
                    continue;
                }
                unset($c['_periode']);
                SidRosterBannedMaster::create($c);
                $ditambah++;
            }

            if ($ditambah > 0) {
                Log::info("DmsRoster auto-banned: {$ditambah} karyawan periode kerja > 70 hari dimasukkan ke sid_roster_banned_master.");
            }

            return $ditambah;
        });
    }

    /**
     * @param  list<string>  $sids
     * @param  list<string>  $niks
     * @return array{0: array<string, true>, 1: array<string, true>}
     */
    private function sudahTercatat(array $sids, array $niks): array
    {
        // Hanya kasus yang MASIH AKTIF (belum di-unban). Orang yang semua
        // kasusnya sudah di-unban boleh mendapat baris master baru.
        $ada = DmsRosterBannedAktif::query()
            ->where(static fn ($q) => $q->whereIn('m.sid', $sids)->orWhereIn('m.nik', $niks === [] ? [''] : $niks))
            ->get(['m.sid', 'm.nik']);

        $sid = [];
        $nik = [];
        foreach ($ada as $r) {
            if ((string) $r->sid !== '') {
                $sid[trim((string) $r->sid)] = true;
            }
            if ((string) $r->nik !== '') {
                $nik[trim((string) $r->nik)] = true;
            }
        }

        return [$sid, $nik];
    }
}
