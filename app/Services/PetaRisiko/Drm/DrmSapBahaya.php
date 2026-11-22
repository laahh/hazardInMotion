<?php

namespace App\Services\PetaRisiko\Drm;

use App\Services\SportEvaluation\RfidClickHouseClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Bukti SAP satu baris "kelompok aktivitas × bahaya-risiko" pada satu minggu kalender, untuk modal kartu KPI.
 * Aturan per jenis laporan sama dengan sync (DrmSql, 17 Nov 2026):
 *   label          : Hazard / Inspeksi yang "ketidaksesuaian / sub"-nya = label M09 yang dipetakan ke baris ini → baris
 *   obs            : OBS yang jenis kegiatannya = label M11 yang dipetakan ke baris ini, atau ke baris lain dengan
 *                    aktivitas + sub aktivitas yang sama                                                    → aktivitas
 *   oak            : OAK yang "aktivitas / sub aktivitas"-nya = label M12, dengan perluasan yang sama        → aktivitas
 * Label OBS / OAK dicocokkan dengan kunci DrmMaster::normTeks (huruf + angka, "Risk Intervention Center" = "Langsung"),
 * mengikuti dokumentasi Risk Matrix 5x5. Semua laporan dari obds.ch_rfid_sap_cuti_all_site. Hanya detail lokasi
 * cakupan (site / grup) yang grup lokasinya memuat bahaya ini (gerbang M05). Selesai = CLOSED / DONE (REJECT terbuka); BUKAN TEMUAN dan DELETED dibuang.
 * Satu laporan = satu task (nomor tasklist): ch_rfid memuat baris kembar per task (gabungan RFID), jadi daftar & semua
 * hitungan memakai task unik (LIMIT 1 BY task / uniqExact(task)); OAK bcbeats memakai id register (= task).
 */
class DrmSapBahaya
{
    private const STATUS_SELESAI = ['CLOSED', 'DONE'];

    private const MAKS_BARIS = 300;

    /** Batas task yang diambil untuk ringkasan (jumlah, belum closed, berulang); daftar tetap MAKS_BARIS terbaru. */
    private const MAKS_HITUNG = 5000;

    private const CACHE_DETIK = 600;

    public function __construct(private readonly RfidClickHouseClient $ch, private readonly DrmRepository $repo)
    {
    }

    /**
     * @return array{dari: string, sampai: string, ndl: int, laporan: list<array<string, mixed>>, ringkas: array<string, int>, terpotong: bool}
     */
    /** $pja diisi = cakupan hanya detail lokasi PJA itu (tab Beranda PJA), di samping filter site / grup. */
    public function ambil(int $hid, string $kel, string $site, string $grup, int $w, string $pja = ''): array
    {
        $kunci = sprintf('peta-risiko:sap-bahaya:v11:%d:%s:%s:%s:%d:%s:%d', $hid, $kel, md5($site), md5($grup), $w, $pja, (int) @filemtime($this->repo->pathMeta()));

        return Cache::remember($kunci, self::CACHE_DETIK, fn () => $this->hitung($hid, $kel, $site, $grup, $w, $pja));
    }

    /**
     * Minggu, detail lokasi cakupan (gerbang M05), label Hazard (M09) dan label OBS / OAK (M11 / M12) untuk satu baris.
     *
     * @return array{dari: CarbonImmutable, sampai: CarbonImmutable, dl: array<int, array{0: string, 1: string, 2: string, 3: string}>,
     *   labelBahaya: list<int>, cfg: array<string, mixed>, labelObs: list<int>, labelOak: list<int>}
     */
    private function persiapan(int $hid, string $kel, string $site, string $grup, int $w, string $pja = ''): array
    {
        // Cakupan PJA: detail lokasi dari berkas PJA di snapshot (null = tanpa batas PJA).
        $dlPja = $pja === '' ? null : array_flip(array_map('strval', array_column(($this->repo->pja($pja) ?? [])['lokasi'] ?? [], 'dl')));
        ['dl' => $kamusDl, 'minggu' => $minggu, 'sampai' => $sampaiData] = $kamus = $this->kamus();
        // Site per departemen (config site_per_departemen): hanya untuk cakupan site (bukan tab grup / PJA), sama dengan
        // ringkasan site di snapshot — baris yang dirutekan dinilai dari DL mana pun tempatnya, dan keluar dari site lain.
        $rute = (array) ($kamus['rute'] ?? []);
        $pakaiRute = $site !== 'ALL' && $grup === '' && $pja === '' && $rute !== [];
        $ruteSite = $pakaiRute ? ($rute[(string) $hid] ?? null) : null;
        $master = DrmMaster::muat();
        $m = $master->data;
        $cfg = $master->cfgEfektif((array) config('peta_risiko_drm'));
        $cfg = DrmSql::cfgAksesOak($this->ch, $cfg)[0];

        $dari = CarbonImmutable::parse($minggu[$w] ?? $minggu[0]);
        $sampai = min($dari->addDays(6), CarbonImmutable::parse($sampaiData));

        // Detail lokasi cakupan yang grup lokasinya memuat baris ini (gerbang M05).
        $namaGrup = array_map('strval', $m['grup']);
        $grupMemuat = [];
        foreach ($m['grupBaris'] as $gid => $riList) {
            if (in_array($hid, $riList, true)) {
                $grupMemuat[$namaGrup[$gid] ?? ''] = (int) $gid;
            }
        }
        $dl = [];
        foreach ($kamusDl as $id => [$s, $lokasi, $detlok, $g]) {
            $siteCocok = ! $pakaiRute ? ($site === 'ALL' || $s === $site) : ($ruteSite !== null ? $ruteSite === strtoupper($site) : $s === $site);
            if ($siteCocok && isset($grupMemuat[$g]) && ($grup === '' || $g === $grup)
                && ($dlPja === null || isset($dlPja[(string) $id]))) {
                $dl[(int) $id] = [$s, $lokasi, $detlok, $g];
            }
        }
        // Label Hazard / Inspeksi (M09, CRC "k / sk" ternormalisasi) yang dipetakan ke baris ini.
        $teksBahaya = [];
        foreach ($master->labelHazard($cfg) as $l) {
            if (in_array($hid, $l['ri'], true)) {
                $teksBahaya[DrmMaster::crc(DrmMaster::norm($l['k']).' / '.DrmMaster::norm($l['sk']))] = trim($l['k']).' / '.trim($l['sk']);
            }
        }
        $labelBahaya = array_keys($teksBahaya);

        // Label OBS (jenis kegiatan, M11) / OAK ("aktivitas / sub", M12) yang menandai baris ini: dipetakan ke baris ini
        // atau ke baris lain dengan aktivitas + sub aktivitas yang sama (DrmMaster::labelTeksAktivitas, sama dengan sync).
        $teks = fn (array $daftar) => array_column(array_filter($daftar, fn (array $t) => in_array($hid, $t['ri'], true)), 'teks', 'crc');
        $teksObs = $teks($master->labelTeksAktivitas('obs'));
        $teksOak = $teks($master->labelTeksAktivitas('oak'));
        $labelObs = array_keys($teksObs);
        $labelOak = array_keys($teksOak);
        // Kurasi aktivitas OAK → aktivitas HIRA (config oak_aktivitas_hira): aktivitas OAK yang menandai baris ini.
        $teksOakAkt = array_map(fn (string $a) => 'Aktivitas OAK: '.$a.' (semua sub aktivitas)', $teks($master->labelOakAktivitas($cfg)));
        $labelOakAkt = array_keys($teksOakAkt);
        return compact('dari', 'sampai', 'dl', 'labelBahaya', 'cfg', 'labelObs', 'labelOak', 'teksBahaya', 'teksObs', 'teksOak',
            'labelOakAkt', 'teksOakAkt');
    }

    /** @return array{dari: string, sampai: string, ndl: int, laporan: list<array<string, mixed>>, ringkas: array<string, int>, terpotong: bool} */
    private function hitung(int $hid, string $kel, string $site, string $grup, int $w, string $pja = ''): array
    {
        $p = $this->persiapan($hid, $kel, $site, $grup, $w, $pja);
        ['dari' => $dari, 'sampai' => $sampai, 'dl' => $dl, 'labelBahaya' => $labelBahaya, 'cfg' => $cfg] = $p;
        $kosong = ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(), 'ndl' => 0, 'laporan' => [],
            'ringkas' => ['total' => 0, 'label' => 0, 'obs' => 0, 'oak' => 0,
                'belum_closed' => 0, 'berulang' => 0, 'oak_stop' => 0], 'terpotong' => false];
        if ($dl === []) {
            return $kosong;
        }

        $dlIds = array_keys($dl);
        $buang = (array) $cfg['status_buang'];
        $baris = [];

        // Hazard / Inspeksi: label persis M09.
        foreach ($labelBahaya === [] ? [] : $this->ch->select($this->sqlHazard($dlIds, $dari, $sampai, $labelBahaya, $buang), 60) as $r) {
            $baris[] = [...$r, 'cara' => 'label'];
        }
        // OBS: label persis M11 (ch_rfid).
        if ($p['labelObs'] !== []) {
            foreach ($this->ch->select($this->sqlTeks($dlIds, $dari, $sampai, $p['labelObs'], [], $buang, (array) $cfg['obs_tanpa_aktivitas'], 'laporan'), 60) as $r) {
                $baris[] = [...$r, 'cara' => 'obs'];
            }
        }
        // OAK (bcbeats): label persis M12 per baris (+ kurasi aktivitas OAK hanya bila disetujui di config).
        if ($p['labelOak'] !== [] || $p['labelOakAkt'] !== []) {
            foreach ($this->ch->select($this->sqlOakLabel($p, $dlIds, 'laporan'), 60) as $r) {
                $baris[] = ['cara' => 'oak', 'jenis' => 'OBSERVASI AREA KRITIS', 'waktu' => $r['waktu'], 'dl' => $r['dl'], 'task' => (string) $r['rid'],
                    'isi' => trim($r['akt'].' / '.$r['sub'], ' /'), 'deskripsi' => '',
                    'status' => (int) $r['stop'] === 1 ? 'Stop Pekerjaan' : 'Pekerjaan Dilanjutkan', 'hasil_oak' => '',
                    'stop' => (int) $r['stop'], 'ts' => 0, 'pelapor' => ''];
            }
        }
        // Task unik lintas sumber (jaga-jaga), terbaru dulu.
        usort($baris, fn ($a, $b) => strcmp((string) $b['waktu'], (string) $a['waktu']));
        $unik = [];
        foreach ($baris as $r) {
            $unik[$r['cara'].'|'.($r['task'] ?? '')] ??= $r;
        }
        $baris = array_values($unik);
        $terpotong = count($baris) > self::MAKS_BARIS;
        $pelaporOak = $this->pelaporOak(array_slice($baris, 0, self::MAKS_BARIS), $dari, $sampai);

        // Berulang (sama dengan sync, DrmSql::berandaSpesifik): temuan Hazard/Inspeksi bahaya ini, dan bahaya ini juga
        // root cause insiden (label M09) di lokasi mana pun dalam N hari sebelum temuan.
        $hari = (int) ($cfg['beranda']['berulang_insiden_hari'] ?? 365);
        $tglInsiden = $labelBahaya === [] ? [] : array_column($this->ch->select($this->sqlInsidenRootCause($labelBahaya, $dari->subDays($hari), $sampai, $cfg), 60), 'tgl');
        sort($tglInsiden);

        $laporan = [];
        $ringkas = $kosong['ringkas'];
        foreach ($baris as $i => $r) {
            $hazard = in_array($r['jenis'], ['HAZARD', 'INSPEKSI'], true);
            $belum = $hazard && ! in_array(strtoupper((string) $r['status']), self::STATUS_SELESAI, true);
            $ulang = $r['cara'] === 'label' && self::adaInsidenSebelum($tglInsiden, substr((string) $r['waktu'], 0, 10), $hari);
            $stop = (int) ($r['stop'] ?? 0) === 1;
            $tsx = (int) ($r['ts'] ?? 0) > 0;
            [$s, $lokasi, $detlok] = $dl[(int) $r['dl']] ?? ['', '', ''];

            $ringkas['total']++;
            $ringkas[$r['cara']]++;
            $ringkas['belum_closed'] += (int) $belum;
            $ringkas['berulang'] += (int) $ulang;
            $ringkas['oak_stop'] += (int) $stop;
            if ($i >= self::MAKS_BARIS) {
                continue;
            }
            $laporan[] = [
                'task' => (string) ($r['task'] ?? ''),
                'waktu' => substr((string) $r['waktu'], 0, 16),
                'jenis' => (string) $r['jenis'],
                'cara' => (string) $r['cara'],
                'lokasi' => trim("{$s} · {$lokasi} · {$detlok}", ' ·'),
                'isi' => (string) $r['isi'],
                'deskripsi' => (string) $r['deskripsi'],
                'status' => (string) $r['status'],
                'hasil_oak' => (string) ($r['hasil_oak'] ?? ''),
                'pelapor' => (string) (($r['pelapor'] ?? '') !== '' ? $r['pelapor'] : ($pelaporOak[(string) ($r['task'] ?? '')] ?? '')),
                'belum_closed' => $belum,
                'berulang' => $ulang,
                'oak_stop' => $stop,
                'tidak_sesuai' => $tsx,
            ];
        }

        return [...$kosong, 'ndl' => count($dl), 'laporan' => $laporan, 'ringkas' => $ringkas, 'terpotong' => $terpotong];
    }

    /**
     * Nama pelapor OAK (register bcbeats tidak memuat nama): diambil dari ch_rfid lewat task = id register.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, string>  task → pelapor
     */
    private function pelaporOak(array $baris, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $task = array_values(array_unique(array_filter(array_map(fn ($r) => $r['cara'] === 'oak' ? (string) ($r['task'] ?? '') : '', $baris),
            fn ($t) => preg_match('/^[0-9]+$/', $t) === 1)));
        if ($task === []) {
            return [];
        }
        $q = [RfidClickHouseClient::class, 'quote'];
        try {
            $rows = $this->ch->select('SELECT task, any(ifNull(pelapor, \'\')) AS pelapor FROM obds.ch_rfid_sap_cuti_all_site
WHERE jenis_data = \'OBSERVASI AREA KRITIS\' AND task IN ('.implode(', ', array_map($q, $task)).')
  AND `date time` >= toDate('.$q($dari->toDateString()).') AND `date time` < toDate('.$q($sampai->toDateString()).') + 1
GROUP BY task', 60);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        return array_column(array_map(fn ($r) => ['t' => (string) $r['task'], 'p' => (string) $r['pelapor']], $rows), 'p', 't');
    }

    /**
     * Item sumber (modal Baseline) = MAPPING baris ini di master HIRA ↔ SAP, tidak bergantung pada laporan: semua label
     * yang menandai baris, masing-masing dengan jumlah laporan pada minggu terpilih (boleh 0).
     *   obs : jenis kegiatan OBS (M11) yang dipetakan ke baris ini / ke baris lain dengan aktivitas + sub yang sama
     *   oak : "aktivitas / sub aktivitas (checklist)" OAK (M12), perluasan sama (nstop = laporan Stop Pekerjaan)
     *   ket : label "ketidaksesuaian / sub ketidaksesuaian" Hazard / Inspeksi (M09) yang dipetakan ke baris ini
     * n = jumlah laporan, ndl = detail lokasi berbeda (detail lokasi cakupan yang grupnya memuat baris ini).
     *
     * @return array{sumber: string, dari: string, sampai: string, ndl: int, nlabel: int,
     *   item: list<array{isi: string, n: int, ndl: int, nstop: int}>}
     */
    public function item(int $hid, string $kel, string $site, string $grup, int $w, string $sumber, string $pja = ''): array
    {
        $kunci = sprintf('peta-risiko:sap-item:v11:%d:%s:%s:%s:%d:%s:%s:%d', $hid, $kel, md5($site), md5($grup), $w, $sumber, $pja, (int) @filemtime($this->repo->pathMeta()));

        return Cache::remember($kunci, self::CACHE_DETIK, function () use ($hid, $kel, $site, $grup, $w, $sumber, $pja) {
            $p = $this->persiapan($hid, $kel, $site, $grup, $w, $pja);
            $teks = match ($sumber) {
                'ket' => $p['teksBahaya'], 'oak' => $p['teksOak'], default => $p['teksObs'],
            };
            $teksAkt = $sumber === 'oak' ? $p['teksOakAkt'] : [];
            $label = array_keys($teks);
            $dlIds = array_keys($p['dl']);
            $laporan = [];
            $buang = (array) $p['cfg']['status_buang'];
            $tanpa = (array) $p['cfg']['obs_tanpa_aktivitas'];
            if ($dlIds !== [] && $label !== [] && $sumber !== 'oak') {
                $sql = $sumber === 'ket'
                    ? $this->sqlItemLabel($dlIds, $p['dari'], $p['sampai'], $label, $buang)
                    : $this->sqlTeks($dlIds, $p['dari'], $p['sampai'], $sumber === 'obs' ? $label : [], $sumber === 'oak' ? $label : [], $buang, $tanpa, 'item');
                $laporan = array_column($this->ch->select($sql, 60), null, 'kc');
            }
            $item = [];
            if ($sumber === 'oak') {
                // OAK: n = laporan yang labelnya (M12) / aktivitasnya (kurasi disetujui) cocok dengan entri ini.
                $baris = ($dlIds !== [] && ($teks + $teksAkt) !== []) ? $this->ch->select($this->sqlOakLabel($p, $dlIds, 'item'), 60) : [];
                foreach ($teks + $teksAkt as $crc => $isi) {
                    $kolom = isset($teksAkt[$crc]) ? 'ka' : 'kl';
                    $cocok = array_filter($baris, fn ($r) => (int) $r[$kolom] === (int) $crc);
                    $item[] = ['isi' => (string) $isi, 'n' => array_sum(array_column($cocok, 'n')), 'ndl' => array_sum(array_column($cocok, 'ndl')),
                        'nstop' => array_sum(array_column($cocok, 'nstop'))];
                }
            }
            foreach ($sumber === 'oak' ? [] : $teks as $crc => $isi) {
                $r = $laporan[$crc] ?? [];
                $item[] = ['isi' => (string) $isi, 'n' => (int) ($r['n'] ?? 0), 'ndl' => (int) ($r['ndl'] ?? 0), 'nstop' => (int) ($r['nstop'] ?? 0)];
            }
            usort($item, fn ($a, $b) => $b['n'] <=> $a['n'] ?: strcmp($a['isi'], $b['isi']));

            return ['sumber' => $sumber, 'dari' => $p['dari']->toDateString(), 'sampai' => $p['sampai']->toDateString(),
                'ndl' => count($p['dl']), 'nlabel' => count($teks) + count($teksAkt), 'item' => $item];
        });
    }

    /**
     * OAK (bcbeats) yang menangkap baris ini — sama dengan sync (CTE oakr/oke): label "aktivitas / sub" (kl) ada di
     * M12 baris ini, atau aktivitasnya (ka) ada di kurasi aktivitas OAK yang disetujui (config oak_aktivitas_hira).
     * mode 'laporan' = per OAK, 'item' = per (kl, ka).
     *
     * @param  array<string, mixed>  $p  hasil persiapan
     * @param  list<int>  $dl
     */
    private function sqlOakLabel(array $p, array $dl, string $mode): string
    {
        $q = [RfidClickHouseClient::class, 'quote'];
        $c = $p['cfg']['oak_checklist'];
        $daftar = fn (array $a) => $a === [] ? '0' : implode(',', array_map('intval', $a));

        $dasar = 'SELECT toInt64(x.id) AS rid, toString(x.submit_date) AS waktu, toString(x.id_location) AS dl,
         ifNull(a.name, \'\') AS akt, ifNull(s.name, \'\') AS sub,
         CRC32('.DrmSql::normTeksSql('concat(ifNull(a.name, \'\'), \' / \', ifNull(s.name, \'\'))').') AS kl,
         CRC32('.DrmSql::normTeksSql('ifNull(a.name, \'\')').') AS ka,
         toUInt8(ifNull(x.id_conclusion, 0) = '.(int) $c['stop'].') AS stop
  FROM (
    SELECT * FROM '.DrmSql::tabelOak($p['cfg'], 'register').'
    WHERE toInt64(id_location) IN ('.$daftar($dl).')
      AND submit_date >= toDate('.$q($p['dari']->toDateString()).') AND submit_date < toDate('.$q($p['sampai']->toDateString()).') + 1
  ) x
  LEFT JOIN (SELECT toInt64(id) AS id, any(name) AS name FROM obds.m_oak_activity GROUP BY id) a ON a.id = toInt64(x.id_oak_activity)
  LEFT JOIN (SELECT toInt64(id) AS id, any(name) AS name FROM obds.m_oak_sub_activity GROUP BY id) s ON s.id = toInt64(x.id_oak_sub_activity)';
        $saring = 'WHERE kl IN ('.$daftar($p['labelOak']).') OR ka IN ('.$daftar($p['labelOakAkt']).')';

        if ($mode === 'item') {
            return 'SELECT kl, ka, count() AS n, uniqExact(dl) AS ndl, countIf(stop = 1) AS nstop
FROM ('.$dasar.') '.$saring.' GROUP BY kl, ka LIMIT 500';
        }

        return 'SELECT * FROM ('.$dasar.') '.$saring.' ORDER BY waktu DESC LIMIT 1 BY rid LIMIT '.self::MAKS_HITUNG;
    }

    /**
     * Laporan OBS / OAK (ch_rfid_sap_cuti_all_site) yang labelnya — jenis kegiatan OBS / "aktivitas / sub" OAK,
     * kunci DrmMaster::normTeks — termasuk $obs / $oak. mode 'laporan' = satu baris per laporan, 'item' = per label.
     *
     * @param  list<int>  $dl
     * @param  list<int>  $obs  CRC label OBS (M11) baris ini
     * @param  list<int>  $oak  CRC label OAK (M12) baris ini
     * @param  list<int>  $oakAkt  CRC aktivitas OAK (kurasi oak_aktivitas_hira) yang menandai baris ini
     *   mode 'item_akt' = per aktivitas OAK (ka)
     */
    private function sqlTeks(array $dl, CarbonImmutable $dari, CarbonImmutable $sampai, array $obs, array $oak, array $buang, array $tanpaAktivitas, string $mode, array $oakAkt = []): string
    {
        $q = [RfidClickHouseClient::class, 'quote'];
        $daftar = fn (array $a) => $a === [] ? '0' : implode(',', array_map('intval', $a));

        $dasar = 'SELECT toString(`date time`) AS waktu, jenis_data AS jenis, toString(`id detail lokasi`) AS dl, ifNull(task, \'\') AS task,
         if(jenis_data = \'OBSERVASI\', trim(ifNull(`aktivitas  OBS`, \'\')),
            concat(trim(ifNull(`aktivitas pekerjaan OAK`, \'\')), \' / \', trim(ifNull(`sub aktivitas pekerjaan OAK`, \'\')))) AS isi,
         CRC32('.DrmSql::normTeksSql('isi').') AS kc,
         CRC32('.DrmSql::normTeksSql('trim(ifNull(`aktivitas pekerjaan OAK`, \'\'))').') AS ka,
         multiSearchAny(lower(concat(ifNull(deskripsi, \'\'), \' \', ifNull(`umpan_balik  OBS`, \'\'), \' \', ifNull(tindakan, \'\'))),
                        ['.implode(', ', array_map($q, $tanpaAktivitas ?: ['-'])).']) AS na,
         toUInt8(jenis_data = \'OBSERVASI AREA KRITIS\' AND positionCaseInsensitive(ifNull(`hasil OAK`, \'\'), \'stop\') > 0) AS stop,
         ifNull(`hasil OAK`, \'\') AS hasil_oak, ifNull(status, \'\') AS status,
         substring(concat(ifNull(deskripsi, \'\'), \' \', ifNull(`umpan_balik  OBS`, \'\')), 1, 400) AS deskripsi, ifNull(pelapor, \'\') AS pelapor
  FROM obds.ch_rfid_sap_cuti_all_site
  WHERE `id detail lokasi` IN ('.$daftar($dl).')
    AND `date time` >= toDate('.$q($dari->toDateString()).') AND `date time` < toDate('.$q($sampai->toDateString()).') + 1
    AND jenis_data IN (\'OBSERVASI\', \'OBSERVASI AREA KRITIS\')
    AND ifNull(status, \'\') NOT IN ('.implode(', ', array_map($q, $buang ?: ['-'])).')';
        $saring = 'NOT na AND ((jenis = \'OBSERVASI\' AND kc IN ('.$daftar($obs).')) OR (jenis = \'OBSERVASI AREA KRITIS\' AND (kc IN ('.$daftar($oak).') OR ka IN ('.$daftar($oakAkt).'))))';

        if ($mode === 'item_akt') {
            return 'SELECT ka, uniqExact(task) AS n, uniqExact(dl) AS ndl, uniqExactIf(task, stop = 1) AS nstop
FROM ('.$dasar.') WHERE '.$saring.' GROUP BY ka LIMIT 100';
        }
        if ($mode === 'item') {
            return 'SELECT kc, any(isi) AS isi, uniqExact(task) AS n, uniqExact(dl) AS ndl, uniqExactIf(task, stop = 1) AS nstop
FROM ('.$dasar.') WHERE '.$saring.' GROUP BY kc ORDER BY n DESC, isi LIMIT 500';
        }

        return 'SELECT * FROM ('.$dasar.') WHERE '.$saring.' ORDER BY waktu DESC LIMIT 1 BY task LIMIT '.self::MAKS_HITUNG;
    }

    private function saringHazard(array $dl, CarbonImmutable $dari, CarbonImmutable $sampai, array $buang): string
    {
        $q = [RfidClickHouseClient::class, 'quote'];

        return 'WHERE `id detail lokasi` IN ('.implode(',', array_map('intval', $dl)).')
    AND `date time` >= toDate('.$q($dari->toDateString()).') AND `date time` < toDate('.$q($sampai->toDateString()).') + 1
    AND jenis_data IN (\'HAZARD\', \'INSPEKSI\')
    AND ifNull(status, \'\') NOT IN ('.implode(', ', array_map($q, $buang ?: ['-'])).')';
    }

    private function crcLabel(): string
    {
        $norm = fn (string $x) => "trimBoth(replaceRegexpAll(lowerUTF8(ifNull({$x}, '')), '\\\\s+', ' '))";

        return 'CRC32(concat('.$norm('ketidaksesuaian').', \' / \', '.$norm('subketidaksesuaian').'))';
    }

    /**
     * Laporan Hazard / Inspeksi yang labelnya termasuk $label (label M09 baris ini).
     *
     * @param  list<int>  $dl
     * @param  list<int>  $label  CRC label "k / sk" ternormalisasi (bahaya ini + label spesifik sekelompok)
     */
    private function sqlHazard(array $dl, CarbonImmutable $dari, CarbonImmutable $sampai, array $label, array $buang): string
    {
        return 'SELECT * FROM (
  SELECT toString(`date time`) AS waktu, jenis_data AS jenis, toString(`id detail lokasi`) AS dl, ifNull(task, \'\') AS task,
         concat(trim(ifNull(ketidaksesuaian, \'\')), \' / \', trim(ifNull(subketidaksesuaian, \'\'))) AS isi,
         '.$this->crcLabel().' AS kc, ifNull(status, \'\') AS status,
         substring(ifNull(deskripsi, \'\'), 1, 400) AS deskripsi, ifNull(pelapor, \'\') AS pelapor
  FROM obds.ch_rfid_sap_cuti_all_site
  '.$this->saringHazard($dl, $dari, $sampai, $buang).'
)
WHERE kc IN ('.implode(',', array_map('intval', $label)).')
ORDER BY waktu DESC
LIMIT 1 BY task
LIMIT '.self::MAKS_HITUNG;
    }

    /**
     * Ada insiden (tanggal Y-m-d, urut naik) pada rentang [tanggal temuan − hari, tanggal temuan]?
     *
     * @param  list<string>  $tglInsiden
     */
    public static function adaInsidenSebelum(array $tglInsiden, string $tanggal, int $hari): bool
    {
        $awal = CarbonImmutable::parse($tanggal)->subDays($hari)->toDateString();
        foreach ($tglInsiden as $t) {
            if ($t > $tanggal) {
                return false;
            }
            if ($t >= $awal) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tanggal insiden yang ROOT CAUSE-nya (ketidaksesuaian + sub) berlabel M09 bahaya ini, di lokasi mana pun.
     * Saringan sama dengan DrmSql rci: detail lokasi insiden harus dikenal di lokasi_histori, kategori buang dikeluarkan.
     *
     * @param  list<int>  $label  CRC label M09
     */
    private function sqlInsidenRootCause(array $label, CarbonImmutable $dari, CarbonImmutable $sampai, array $cfg): string
    {
        $norm = fn (string $x) => "trimBoth(replaceRegexpAll(lowerUTF8(ifNull({$x}, '')), '\\\\s+', ' '))";
        $buang = implode(', ', array_map(fn ($k) => "'".str_replace("'", "\\'", (string) $k)."'", (array) ($cfg['insiden']['kategori_buang'] ?? [])));

        // Alias dalam sengaja "d": alias keluaran "tgl" (String) akan menimpa kolom tanggal di WHERE (ClickHouse).
        return 'SELECT DISTINCT toString(d) AS tgl FROM (
  SELECT toDate(parseDateTimeBestEffortUSOrNull(r.Tanggal_Kejadian)) AS d,
         CRC32(concat('.$norm('r.Ketidaksesuaian').', \' / \', '.$norm('r.Sub_Ketidaksesuaian').')) AS kc
  FROM obds.Hazard_Related_Incident AS r
  WHERE r.Status_Causal = \'ROOT CAUSE\' AND parseDateTimeBestEffortUSOrNull(r.Tanggal_Kejadian) IS NOT NULL
    AND ifNull(r.Kategori_Incident, \'\') NOT IN ('.($buang === '' ? "''" : $buang).')
    AND (lower(trim(r.Site)), lower(trim(r.Lokasi)), lower(trim(r.Detail_Lokasi))) IN (
      SELECT lower(trim(Site)), lower(trim(Lokasi)), lower(trim(`Detil Lokasi`)) FROM obds.lokasi_histori)
)
WHERE d >= toDate(\''.$dari->toDateString().'\') AND d <= toDate(\''.$sampai->toDateString().'\')
  AND kc IN ('.implode(',', array_map('intval', $label)).')
ORDER BY tgl';
    }

    /** @param list<int> $dl  @param list<int> $label */
    private function sqlItemLabel(array $dl, CarbonImmutable $dari, CarbonImmutable $sampai, array $label, array $buang): string
    {
        return 'SELECT '.$this->crcLabel().' AS kc, any(concat(trim(ifNull(ketidaksesuaian, \'\')), \' / \', trim(ifNull(subketidaksesuaian, \'\')))) AS isi,
       uniqExact(task) AS n, uniqExact(`id detail lokasi`) AS ndl
FROM obds.ch_rfid_sap_cuti_all_site
'.$this->saringHazard($dl, $dari, $sampai, $buang).'
    AND kc IN ('.implode(',', array_map('intval', $label)).')
GROUP BY kc ORDER BY n DESC LIMIT 500';
    }

    /**
     * Kamus detail lokasi + minggu dari meta snapshot, di-cache per versi snapshot (meta.json besar).
     *
     * @return array{dl: array<string, array{0: string, 1: string, 2: string, 3: string}>, minggu: list<string>, sampai: string}
     */
    private function kamus(): array
    {
        return Cache::remember('peta-risiko:kamus-dl:v2:'.(int) @filemtime($this->repo->pathMeta()), 3600, function () {
            $meta = $this->repo->meta() ?? [];

            return [
                'dl' => (array) ($meta['beranda']['dl'] ?? []),
                'minggu' => (array) ($meta['beranda']['minggu'] ?? []),
                'sampai' => (string) ($meta['sampai'] ?? now()->toDateString()),
                'rute' => array_map('strval', (array) ($meta['beranda']['ruteSite'] ?? [])),
            ];
        });
    }
}
