<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Edit Roster: mengubah jadwal roster yang sudah di-upload langsung dari web
 * (menu Master Roster → Edit Roster), hanya untuk user yang di-assign ke
 * perusahaannya (lihat DmsRosterAksesPerusahaan::assignment()).
 *
 * Yang bisa diubah:
 *   - kode satu tanggal (D1–D13, N1–N7, OFF, CT, S, I) atau dikosongkan;
 *   - Jam Kerja Awal / Akhir / Total satu karyawan — berlaku untuk semua hari
 *     kerjanya di roster itu, sama dengan arti kolom itu di template Excel.
 *
 * Jam per hari dihitung ulang dengan aturan yang sama dengan upload: hari
 * kerja memakai jam karyawan bila ada, kalau tidak jam bawaan kodenya; hari
 * bukan kerja tanpa jam. Status roster tidak berubah (perubahan langsung
 * berlaku); setiap perubahan dicatat di dms_roster_master_edit_log bila
 * tabelnya sudah dimigrasi.
 */
final class DmsRosterMasterEditService
{
    public const TABEL_LOG = 'dms_roster_master_edit_log';

    /** Batas perubahan per simpan — jaga transaksi tetap kecil. */
    public const MAKS_PERUBAHAN = 5000;

    /**
     * Simpan perubahan. $ejaan = ejaan perusahaan yang boleh diedit user ini
     * (DmsRosterAksesPerusahaan::ejaanCakupan()); SID perusahaan lain ditolak.
     *
     * @param  list<array{sid:string,tanggal:string,kode:string}>  $sel
     * @param  list<array{sid:string,masuk:string,keluar:string,total:string}>  $jam
     * @param  list<string>  $ejaan
     * @return array{galat: list<string>, ubah: int}
     */
    public function simpan(DmsRosterMasterUpload $upload, array $sel, array $jam, array $ejaan, string $oleh): array
    {
        $sids = array_values(array_unique(array_merge(array_column($sel, 'sid'), array_column($jam, 'sid'))));
        if ($sids === []) {
            return ['galat' => [], 'ubah' => 0];
        }

        $adaTotal = DmsRosterMasterService::adaKolomTotal();
        $baris = [];
        $identitas = [];
        foreach (DB::table('dms_roster_master_jadwal')
            ->where('upload_id', $upload->id)
            ->whereIn('kode_sid', $sids)
            ->whereIn(DB::raw('TRIM(perusahaan)'), $ejaan === [] ? [''] : $ejaan)
            ->get() as $r) {
            $sid = (string) $r->kode_sid;
            $identitas[$sid] ??= ['nama' => $r->nama, 'perusahaan' => $r->perusahaan, 'jabatan' => $r->jabatan, 'site' => $r->site];
            $baris[$sid][CarbonImmutable::parse((string) $r->tanggal)->toDateString()] = self::barisJadwal($r, $adaTotal);
        }

        $rencana = self::rencana(
            $baris,
            $sel,
            $jam,
            CarbonImmutable::parse($upload->periode_mulai)->toDateString(),
            CarbonImmutable::parse($upload->periode_selesai)->toDateString(),
        );
        if ($rencana['galat'] !== [] || $rencana['ops'] === []) {
            return ['galat' => $rencana['galat'], 'ubah' => 0];
        }

        DB::transaction(function () use ($upload, $rencana, $identitas, $adaTotal, $oleh): void {
            foreach ($rencana['ops'] as [$aksi, $sid, $tgl, $h]) {
                $kunci = ['upload_id' => $upload->id, 'kode_sid' => $sid, 'tanggal' => $tgl];
                if ($aksi === 'hapus') {
                    DB::table('dms_roster_master_jadwal')->where($kunci)->delete();

                    continue;
                }
                $isi = [
                    'kode' => $h['kode'],
                    'jam_masuk' => $h['masuk'],
                    'jam_keluar' => $h['keluar'],
                    'menit_kerja' => $h['menit'],
                ] + ($adaTotal ? ['menit_total_kerja' => $h['total']] : []);
                if ($aksi === 'tambah') {
                    DB::table('dms_roster_master_jadwal')->insert($kunci + $identitas[$sid] + $isi);
                } else {
                    DB::table('dms_roster_master_jadwal')->where($kunci)->update($isi);
                }
            }

            $upload->update([
                'jumlah_jadwal' => DB::table('dms_roster_master_jadwal')->where('upload_id', $upload->id)->count(),
                'jumlah_karyawan' => DB::table('dms_roster_master_jadwal')->where('upload_id', $upload->id)->distinct()->count('kode_sid'),
            ]);
            $this->catat($upload->id, $rencana['log'], $oleh);
        });

        return ['galat' => [], 'ubah' => count($rencana['ops'])];
    }

    /**
     * Riwayat edit terbaru satu roster (kosong bila tabel log belum dimigrasi).
     *
     * @return list<object>
     */
    public function riwayat(int $uploadId, int $batas = 30): array
    {
        if (! self::adaLog()) {
            return [];
        }

        return DB::table(self::TABEL_LOG)->where('upload_id', $uploadId)
            ->orderByDesc('id')->limit($batas)->get()->all();
    }

    public static function adaLog(): bool
    {
        try {
            return Schema::hasTable(self::TABEL_LOG);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Rencana perubahan — murni, tanpa DB.
     *
     * @param  array<string, array<string, array{kode:string,masuk:?string,keluar:?string,menit:?int,total:?int}>>  $baris  SID => tanggal => jadwal sekarang
     * @param  list<array{sid:string,tanggal:string,kode:string}>  $sel  kode '' = kosongkan tanggal itu
     * @param  list<array{sid:string,masuk:string,keluar:string,total:string}>  $jam  masuk & keluar '' = ikut jam bawaan kode
     * @return array{galat: list<string>, ops: list<array{0:string,1:string,2:string,3:?array<string, mixed>}>, log: list<array<string, ?string>>}
     */
    public static function rencana(array $baris, array $sel, array $jam, string $dari, string $sampai): array
    {
        $galat = [];
        if (count($sel) + count($jam) > self::MAKS_PERUBAHAN) {
            return ['galat' => ['Terlalu banyak perubahan sekaligus (maks '.self::MAKS_PERUBAHAN.'). Simpan bertahap.'], 'ops' => [], 'log' => []];
        }

        // 1. Jam kerja per karyawan: baru dari form, atau dibaca dari jadwal sekarang.
        $jamOrang = [];
        $jamBaru = [];
        foreach ($jam as $j) {
            $sid = (string) $j['sid'];
            if (! isset($baris[$sid])) {
                $galat[] = 'SID '.$sid.' tidak ada di roster ini atau bukan perusahaan Anda.';

                continue;
            }
            $hasil = self::jamDariForm($j);
            if (is_string($hasil)) {
                $galat[] = $sid.': '.$hasil;

                continue;
            }
            $jamBaru[$sid] = $hasil;
        }
        foreach (array_keys($baris) as $sid) {
            $jamOrang[$sid] = array_key_exists($sid, $jamBaru) ? $jamBaru[$sid] : self::jamDariJadwal($baris[$sid]);
        }

        // 2. Kode baru per (SID, tanggal).
        $target = [];
        foreach ($sel as $s) {
            $sid = (string) $s['sid'];
            $tgl = (string) $s['tanggal'];
            $kode = strtoupper(trim((string) $s['kode']));
            if (! isset($baris[$sid])) {
                $galat[] = 'SID '.$sid.' tidak ada di roster ini atau bukan perusahaan Anda.';

                continue;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) !== 1 || $tgl < $dari || $tgl > $sampai) {
                $galat[] = $sid.': tanggal '.$tgl.' di luar periode roster ('.$dari.' s/d '.$sampai.').';

                continue;
            }
            if ($kode !== '' && ! isset(DmsRosterMasterService::KODE[$kode])) {
                $galat[] = $sid.' '.$tgl.': kode "'.$kode.'" tidak dikenal.';

                continue;
            }
            $target[$sid][$tgl] = $kode;
        }
        if ($galat !== []) {
            return ['galat' => array_values(array_unique($galat)), 'ops' => [], 'log' => []];
        }

        // 3. Bandingkan jadwal sekarang dengan jadwal yang diinginkan.
        $ops = [];
        $log = [];
        foreach (array_unique(array_merge(array_keys($target), array_keys($jamBaru))) as $sid) {
            $tanggal = array_key_exists($sid, $jamBaru)
                ? array_unique(array_merge(array_keys($baris[$sid]), array_keys($target[$sid] ?? [])))
                : array_keys($target[$sid] ?? []);
            sort($tanggal);

            foreach ($tanggal as $tgl) {
                $lama = $baris[$sid][$tgl] ?? null;
                $kode = $target[$sid][$tgl] ?? ($lama['kode'] ?? '');
                if ($kode === '') {
                    if ($lama !== null) {
                        $ops[] = ['hapus', $sid, $tgl, null];
                        $log[] = self::log($sid, $tgl, 'kode', $lama['kode'], null);
                    }

                    continue;
                }
                $baru = self::jamHari($kode, $jamOrang[$sid]);
                if ($lama === null) {
                    $ops[] = ['tambah', $sid, $tgl, $baru];
                    $log[] = self::log($sid, $tgl, 'kode', null, $kode);
                } elseif ($baru != $lama) {
                    $ops[] = ['ubah', $sid, $tgl, $baru];
                    if ($baru['kode'] !== $lama['kode']) {
                        $log[] = self::log($sid, $tgl, 'kode', $lama['kode'], $baru['kode']);
                    }
                }
            }
        }
        foreach ($jamBaru as $sid => $j) {
            $lama = self::jamDariJadwal($baris[$sid]);
            if ($lama != $j) {
                $log[] = self::log($sid, null, 'jam', self::teksJam($lama), self::teksJam($j));
            }
        }

        return ['galat' => [], 'ops' => $ops, 'log' => $log];
    }

    /**
     * Jam karyawan dari form. Awal & Akhir kosong = ikut jam bawaan kode
     * (null); Total kosong = Akhir − Awal; Total boleh lebih kecil (istirahat).
     *
     * @param  array{masuk:string,keluar:string,total:string}  $j
     * @return array{masuk:string,keluar:string,menit:int,total:int}|string|null  string = pesan galat
     */
    public static function jamDariForm(array $j): array|string|null
    {
        $masuk = trim((string) ($j['masuk'] ?? ''));
        $keluar = trim((string) ($j['keluar'] ?? ''));
        $total = str_replace(',', '.', trim((string) ($j['total'] ?? '')));

        if ($masuk === '' && $keluar === '') {
            return $total === '' ? null : 'Total Jam Kerja diisi tanpa Jam Kerja Awal & Akhir.';
        }
        $pola = '/^([01]\d|2[0-3]):[0-5]\d$/';
        if (preg_match($pola, $masuk) !== 1 || preg_match($pola, $keluar) !== 1) {
            return 'Jam Kerja Awal dan Akhir harus diisi berdua dengan format HH:MM.';
        }
        $menit = self::selisihMenit($masuk, $keluar);
        if ($menit === 0) {
            return 'Jam Kerja Awal dan Akhir tidak boleh sama.';
        }
        $totalMenit = $menit;
        if ($total !== '') {
            $totalMenit = is_numeric($total) ? (int) round((float) $total * 60) : 0;
            if ($totalMenit <= 0 || $totalMenit > $menit) {
                return 'Total Jam Kerja harus lebih dari 0 dan tidak lebih dari '.round($menit / 60, 2).' jam ('.$masuk.'–'.$keluar.').';
            }
        }

        return ['masuk' => $masuk, 'keluar' => $keluar, 'menit' => $menit, 'total' => $totalMenit];
    }

    /**
     * Jam karyawan yang sedang berlaku di jadwalnya (kebalikan jamHari):
     * terisi bila semua hari kerja memakai satu jam khusus.
     *
     * @param  array<string, array{kode:string,masuk:?string,keluar:?string,menit:?int,total:?int}>  $jadwal
     * @return array{masuk:string,keluar:string,menit:int,total:int}|null
     */
    public static function jamDariJadwal(array $jadwal): ?array
    {
        [$masuk, $keluar] = DmsRosterMasterService::jamKaryawanDariJadwal($jadwal);
        if ($masuk === null || $keluar === null) {
            return null;
        }
        $menit = self::selisihMenit($masuk, $keluar);
        $total = DmsRosterMasterService::totalKaryawanDariJadwal($jadwal);

        return ['masuk' => $masuk, 'keluar' => $keluar, 'menit' => $menit, 'total' => $total === null ? $menit : (int) round($total * 60)];
    }

    /**
     * Jam satu hari — aturan sama dengan upload (DmsRosterMasterService::jamHari).
     *
     * @param  array{masuk:string,keluar:string,menit:int,total:int}|null  $jamOrang
     * @return array{kode:string,masuk:?string,keluar:?string,menit:?int,total:?int}
     */
    public static function jamHari(string $kode, ?array $jamOrang): array
    {
        [, , $masuk, $keluar] = DmsRosterMasterService::KODE[$kode];
        if ($masuk === null) {
            return ['kode' => $kode, 'masuk' => null, 'keluar' => null, 'menit' => null, 'total' => null];
        }
        if ($jamOrang !== null) {
            return ['kode' => $kode] + $jamOrang;
        }
        $menit = self::selisihMenit($masuk, $keluar);

        return ['kode' => $kode, 'masuk' => $masuk, 'keluar' => $keluar, 'menit' => $menit, 'total' => $menit];
    }

    /**
     * @return array{kode:string,masuk:?string,keluar:?string,menit:?int,total:?int}
     */
    private static function barisJadwal(object $r, bool $adaTotal): array
    {
        $menit = $r->menit_kerja === null ? null : (int) $r->menit_kerja;

        return [
            'kode' => (string) $r->kode,
            'masuk' => $r->jam_masuk === null ? null : substr((string) $r->jam_masuk, 0, 5),
            'keluar' => $r->jam_keluar === null ? null : substr((string) $r->jam_keluar, 0, 5),
            'menit' => $menit,
            // Total kosong / belum dimigrasi = sama dengan menit kerja.
            'total' => $adaTotal && isset($r->menit_total_kerja) ? (int) $r->menit_total_kerja : $menit,
        ];
    }

    private static function selisihMenit(string $masuk, string $keluar): int
    {
        $m = static fn (string $j): int => (int) substr($j, 0, 2) * 60 + (int) substr($j, 3, 2);
        $d = $m($keluar) - $m($masuk);

        return $d < 0 ? $d + 1440 : $d;
    }

    /**
     * @param  array{masuk:string,keluar:string,menit:int,total:int}|null  $j
     */
    private static function teksJam(?array $j): string
    {
        return $j === null ? 'bawaan kode' : $j['masuk'].'–'.$j['keluar'].' ('.rtrim(rtrim(number_format($j['total'] / 60, 2, '.', ''), '0'), '.').' j)';
    }

    /**
     * @return array<string, ?string>
     */
    private static function log(string $sid, ?string $tgl, string $kolom, ?string $lama, ?string $baru): array
    {
        return ['kode_sid' => $sid, 'tanggal' => $tgl, 'kolom' => $kolom, 'nilai_lama' => $lama, 'nilai_baru' => $baru];
    }

    /**
     * @param  list<array<string, ?string>>  $log
     */
    private function catat(int $uploadId, array $log, string $oleh): void
    {
        if ($log === [] || ! self::adaLog()) {
            return;
        }
        $kini = now();
        foreach (array_chunk($log, 500) as $potong) {
            DB::table(self::TABEL_LOG)->insert(array_map(static fn (array $l): array => $l + [
                'upload_id' => $uploadId,
                'diubah_oleh' => mb_substr($oleh, 0, 150),
                'created_at' => $kini,
            ], $potong));
        }
    }
}
