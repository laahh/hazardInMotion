<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Edit Roster: satu tabel gabungan SEMUA roster yang di-upload (menunggu
 * approval & disetujui; yang ditolak tidak) untuk perusahaan user, diedit
 * langsung dari web (menu Master Roster → Edit Roster). Hanya untuk user yang
 * di-assign ke perusahaannya (DmsRosterAksesPerusahaan::assignment()).
 *
 * Gabungan: bila satu (SID, tanggal) ada di beberapa upload, yang dipakai
 * baris dari upload TERBARU (created_at, lalu id). Identitas karyawan (nama,
 * jabatan, site) juga dari upload terbarunya.
 *
 * Simpan:
 *   - ubah kode   → baris pemenang (upload terbaru) diubah;
 *   - isi kosong  → ditambahkan ke upload terbaru karyawan itu yang
 *                   periodenya mencakup tanggal tsb (tidak ada = ditolak);
 *   - kosongkan   → baris tanggal itu dihapus dari SEMUA upload perusahaan
 *                   user, supaya jadwal lama di bawahnya tidak muncul lagi;
 *   - jam kerja   → Jam Kerja Awal / Akhir / Total satu karyawan, berlaku
 *                   untuk semua hari kerjanya di tabel gabungan.
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
     * Baris jadwal perusahaan user dari upload yang tidak ditolak, upload
     * terbaru dulu — baris pertama per (SID, tanggal) adalah pemenangnya.
     *
     * @param  list<string>|null  $ejaan  null = semua perusahaan (hanya untuk dilihat)
     */
    private function dasar(?array $ejaan): Builder
    {
        return DB::table('dms_roster_master_jadwal as j')
            ->join('dms_roster_master_upload as u', 'u.id', '=', 'j.upload_id')
            ->where('u.status', '<>', DmsRosterMasterUpload::STATUS_REJECTED)
            ->when($ejaan !== null, static fn ($q) => $q->whereIn(DB::raw('TRIM(j.perusahaan)'), $ejaan === [] ? [''] : $ejaan));
    }

    /**
     * Rentang tanggal seluruh jadwal gabungan (untuk navigasi bulan).
     *
     * @param  list<string>|null  $ejaan  null = semua perusahaan
     * @return array{dari:CarbonImmutable,sampai:CarbonImmutable}|null
     */
    public function rentang(?array $ejaan): ?array
    {
        $r = $this->dasar($ejaan)->selectRaw('MIN(j.tanggal) AS dari, MAX(j.tanggal) AS sampai')->first();

        return $r === null || $r->dari === null ? null
            : ['dari' => CarbonImmutable::parse((string) $r->dari), 'sampai' => CarbonImmutable::parse((string) $r->sampai)];
    }

    /**
     * Satu halaman tabel gabungan untuk rentang tanggal: karyawan yang punya
     * jadwal di rentang itu, urut SID.
     *
     * @param  list<string>|null  $ejaan  null = semua perusahaan
     * @param  string  $site  '' = semua site; dicocokkan tanpa beda huruf & spasi ("BMO 1" = "bmo1")
     * @return array{karyawan: list<array<string, mixed>>, total: int}
     */
    public function grid(?array $ejaan, CarbonImmutable $dari, CarbonImmutable $sampai, string $cari, int $halaman, int $perHalaman, string $site = ''): array
    {
        $rentang = $this->dasar($ejaan)->whereBetween('j.tanggal', [$dari->toDateString(), $sampai->toDateString()]);
        if ($site !== '') {
            $rentang->whereRaw("UPPER(REPLACE(TRIM(j.site), ' ', '')) = ?", [mb_strtoupper(str_replace(' ', '', trim($site)))]);
        }
        if ($cari !== '') {
            $mirip = '%'.addcslashes($cari, '%_\\').'%';
            $rentang->where(static fn ($q) => $q->where('j.kode_sid', 'like', $mirip)->orWhere('j.nama', 'like', $mirip));
        }
        $total = (clone $rentang)->distinct()->count('j.kode_sid');
        $sids = (clone $rentang)->select('j.kode_sid')->distinct()->orderBy('j.kode_sid')
            ->offset(max(0, $halaman - 1) * $perHalaman)->limit($perHalaman)->pluck('kode_sid')->all();
        if ($sids === []) {
            return ['karyawan' => [], 'total' => $total];
        }

        $adaTotal = DmsRosterMasterService::adaKolomTotal();
        $karyawan = [];
        foreach ($this->dasar($ejaan)
            ->whereIn('j.kode_sid', $sids)
            ->whereBetween('j.tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->orderBy('j.kode_sid')->orderByDesc('u.created_at')->orderByDesc('u.id')
            ->get(['j.*', 'u.status']) as $r) {
            $sid = (string) $r->kode_sid;
            $tgl = CarbonImmutable::parse((string) $r->tanggal)->toDateString();
            // Baris pertama per SID = upload terbaru → identitasnya yang dipakai.
            $karyawan[$sid] ??= ['sid' => $sid, 'nama' => $r->nama, 'perusahaan' => $r->perusahaan, 'jabatan' => $r->jabatan, 'site' => $r->site, 'jadwal' => []];
            if (! isset($karyawan[$sid]['jadwal'][$tgl])) {
                $karyawan[$sid]['jadwal'][$tgl] = self::barisJadwal($r, $adaTotal)
                    + ['menunggu' => $r->status === DmsRosterMasterUpload::STATUS_PENDING];
            }
        }

        foreach ($karyawan as &$k) {
            [$k['jam_awal'], $k['jam_akhir']] = DmsRosterMasterService::jamKaryawanDariJadwal($k['jadwal']);
            $k['jam_total'] = DmsRosterMasterService::totalKaryawanDariJadwal($k['jadwal']);
        }
        unset($k);

        return ['karyawan' => array_values($karyawan), 'total' => $total];
    }

    /**
     * Simpan perubahan tabel gabungan. $ejaan = ejaan perusahaan yang boleh
     * diedit user ini (DmsRosterAksesPerusahaan::ejaanCakupan()); SID
     * perusahaan lain ditolak.
     *
     * @param  list<array{sid:string,tanggal:string,kode:string}>  $sel
     * @param  list<array{sid:string,masuk:string,keluar:string,total:string}>  $jam
     * @param  list<string>  $ejaan
     * @return array{galat: list<string>, ubah: int}
     */
    public function simpan(array $sel, array $jam, array $ejaan, string $oleh): array
    {
        $sids = array_values(array_unique(array_merge(array_column($sel, 'sid'), array_column($jam, 'sid'))));
        if ($sids === []) {
            return ['galat' => [], 'ubah' => 0];
        }

        // Semua baris SID ini (semua tanggal) — upload terbaru dulu.
        $adaTotal = DmsRosterMasterService::adaKolomTotal();
        $baris = [];
        $pemenang = [];
        $semuaUpload = [];
        $identitas = [];
        $uploadSid = [];
        foreach ($this->dasar($ejaan)->whereIn('j.kode_sid', $sids)
            ->orderByDesc('u.created_at')->orderByDesc('u.id')
            ->get(['j.*', 'u.periode_mulai', 'u.periode_selesai']) as $r) {
            $sid = (string) $r->kode_sid;
            $tgl = CarbonImmutable::parse((string) $r->tanggal)->toDateString();
            $id = (int) $r->upload_id;
            $identitas[$sid] ??= ['nama' => $r->nama, 'perusahaan' => $r->perusahaan, 'jabatan' => $r->jabatan, 'site' => $r->site];
            $uploadSid[$sid][$id] ??= [
                'id' => $id,
                'dari' => CarbonImmutable::parse((string) $r->periode_mulai)->toDateString(),
                'sampai' => CarbonImmutable::parse((string) $r->periode_selesai)->toDateString(),
            ];
            $semuaUpload[$sid][$tgl][] = $id;
            if (! isset($baris[$sid][$tgl])) {
                $baris[$sid][$tgl] = self::barisJadwal($r, $adaTotal);
                $pemenang[$sid][$tgl] = $id;
            }
        }

        $rencana = self::rencana($baris, $sel, $jam, '1900-01-01', '2999-12-31');
        if ($rencana['galat'] !== [] || $rencana['ops'] === []) {
            return ['galat' => $rencana['galat'], 'ubah' => 0];
        }

        // Terjemahkan ke baris upload yang mana; tolak sel kosong di luar periode semua roster karyawan itu.
        $galat = [];
        $langkah = [];
        foreach ($rencana['ops'] as [$aksi, $sid, $tgl, $h]) {
            if ($aksi === 'ubah') {
                $langkah[] = [$aksi, $sid, $tgl, $h, [$pemenang[$sid][$tgl]]];
            } elseif ($aksi === 'hapus') {
                $langkah[] = [$aksi, $sid, $tgl, $h, array_values(array_unique($semuaUpload[$sid][$tgl] ?? []))];
            } else {
                $target = null;
                foreach ($uploadSid[$sid] ?? [] as $u) {
                    if ($u['dari'] <= $tgl && $tgl <= $u['sampai']) {
                        $target = $u['id'];
                        break;
                    }
                }
                if ($target === null) {
                    $galat[] = $sid.' '.$tgl.': di luar periode semua roster karyawan ini — upload roster untuk periode tersebut.';

                    continue;
                }
                $langkah[] = [$aksi, $sid, $tgl, $h, [$target]];
            }
        }
        if ($galat !== []) {
            return ['galat' => $galat, 'ubah' => 0];
        }

        $uploadLog = [];
        foreach ($langkah as [, $sid, $tgl, , $ids]) {
            $uploadLog[$sid.'|'.$tgl] = $ids[0];
        }

        DB::transaction(function () use ($langkah, $identitas, $adaTotal, $oleh, $rencana, $uploadLog, $uploadSid): void {
            $tersentuh = [];
            foreach ($langkah as [$aksi, $sid, $tgl, $h, $ids]) {
                foreach ($ids as $id) {
                    $tersentuh[$id] = true;
                    $kunci = ['upload_id' => $id, 'kode_sid' => $sid, 'tanggal' => $tgl];
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
            }

            foreach (array_keys($tersentuh) as $id) {
                DmsRosterMasterUpload::whereKey($id)->update([
                    'jumlah_jadwal' => DB::table('dms_roster_master_jadwal')->where('upload_id', $id)->count(),
                    'jumlah_karyawan' => DB::table('dms_roster_master_jadwal')->where('upload_id', $id)->distinct()->count('kode_sid'),
                ]);
            }

            // Log jam (tanpa tanggal) dicatat ke upload terbaru karyawan itu.
            $log = array_map(static fn (array $l): array => $l + [
                'upload_id' => $uploadLog[$l['kode_sid'].'|'.$l['tanggal']] ?? (int) array_key_first($uploadSid[$l['kode_sid']] ?? [0 => 0]),
            ], $rencana['log']);
            $this->catat($log, $oleh);
        });

        return ['galat' => [], 'ubah' => count($rencana['ops'])];
    }

    /**
     * Riwayat edit terbaru untuk roster perusahaan yang dilihat (kosong bila tabel
     * log belum dimigrasi).
     *
     * @param  list<string>|null  $ejaan  null = semua perusahaan
     * @return list<object>
     */
    public function riwayat(?array $ejaan, int $batas = 30): array
    {
        if (! self::adaLog() || $ejaan === []) {
            return [];
        }
        $upload = $ejaan === null ? null : DB::table('dms_roster_master_jadwal')
            ->whereIn(DB::raw('TRIM(perusahaan)'), $ejaan)
            ->distinct()->pluck('upload_id')->all();

        return DB::table(self::TABEL_LOG.' as l')
            ->leftJoin('dms_roster_master_upload as u', 'u.id', '=', 'l.upload_id')
            ->when($upload !== null, static fn ($q) => $q->whereIn('l.upload_id', $upload === [] ? [0] : $upload))
            ->orderByDesc('l.id')->limit($batas)
            ->get(['l.*', 'u.judul'])->all();
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
            // SID angka ("10016025") jadi kunci int di array PHP — kembalikan ke string.
            $sid = (string) $sid;
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
                $log[] = self::log((string) $sid, null, 'jam', self::teksJam($lama), self::teksJam($j));
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
     * @param  list<array<string, mixed>>  $log  tiap baris sudah membawa upload_id
     */
    private function catat(array $log, string $oleh): void
    {
        if ($log === [] || ! self::adaLog()) {
            return;
        }
        $kini = now();
        foreach (array_chunk($log, 500) as $potong) {
            DB::table(self::TABEL_LOG)->insert(array_map(static fn (array $l): array => $l + [
                'diubah_oleh' => mb_substr($oleh, 0, 150),
                'created_at' => $kini,
            ], $potong));
        }
    }
}
