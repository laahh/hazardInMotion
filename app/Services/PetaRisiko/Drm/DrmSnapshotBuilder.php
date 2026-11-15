<?php

namespace App\Services\PetaRisiko\Drm;

use App\Services\SportEvaluation\RfidClickHouseClient;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Menyusun snapshot Peta Risiko PJA (DRM 2.1) dari ClickHouse OBDS.
 * Query berat (aktivitas × detail lokasi, bahaya sorot, temuan open) dijalankan per site.
 *
 * Hasil: ['meta' => [...], 'pja' => [id => [...]]] untuk DrmRepository::simpan().
 */
class DrmSnapshotBuilder
{
    public const KEKERAPAN = ['Hampir Pasti', 'Sangat Mungkin', 'Mungkin', 'Kemungkinan Kecil', 'Langka'];

    public const KONSEKUENSI = ['Tidak Signifikan', 'Minor', 'Sedang', 'Major', 'Bencana'];

    public const LEVEL = ['Low', 'Medium', 'High', 'Significant'];

    /** Kolom baris aktivitas × detail lokasi di file PJA (array posisi, dipakai juga oleh JS). */
    public const KOLOM_AKT = ['dl', 'a', 'bahaya', 'k', 'g', 't', 'x', 'gOpen', 'gUlang', 'gStop', 'sig', 'high', 'terakhir', 'strip', 'viaBukti', 'proses'];

    /** Kolom baris bahaya HIRA di file PJA. */
    public const KOLOM_BAHAYA = ['hid', 'a', 'aktivitas', 'sub', 'bahaya', 'potensi', 'n1', 'k1', 'c1', 'n2', 'k2', 'c2', 'kontrol', 'faktor', 'proses'];

    private const TEMUAN_MAKS_PER_PJA = 400;

    /** @var Closure(string): void */
    private Closure $log;

    public function __construct(private readonly RfidClickHouseClient $ch)
    {
        $this->log = static function (string $pesan): void {
        };
    }

    /** @param Closure(string): void $log */
    public function denganLog(Closure $log): self
    {
        $this->log = $log;

        return $this;
    }

    /** @return array{meta: array<string, mixed>, pja: array<string, array<string, mixed>>} */
    public function bangun(CarbonImmutable $sampai): array
    {
        $cfg = (array) config('peta_risiko_drm');
        $sql = new DrmSql($cfg, $sampai);

        $d = [
            'sites' => array_column($this->ambil('daftar site', $sql->daftarSite()), 'site'),
            'hira' => $this->ambil('baseline HIRA', $sql->hira()),
            'dl' => $this->ambil('detail lokasi', $sql->detailLokasi()),
            'pja' => $this->ambil('master PJA', $sql->pja()),
            'matriks' => $this->ambil('matriks per cakupan (tren mingguan)', $sql->matriksCakupan()),
            'insiden' => $this->ambil('insiden', $sql->insiden()),
            'personil' => $this->ambil('personil PJA', $sql->personil()),
            'bearc' => $this->ambil('BeARC mingguan', $sql->bearc()),
            'coverage' => $this->ambil('coverage', $sql->coverage()),
            'mapping' => $this->ambil('mapping SAP per bahaya (Excel)', $sql->mappingBahaya()),
            'akt' => [], 'sorot' => [], 'temuan' => [], 'statusBahaya' => [],
        ];
        foreach ($d['sites'] as $site) {
            $s = $sql->untukSite($site);
            array_push($d['akt'], ...$this->ambil("aktivitas × detail lokasi {$site}", $s->aktivitasDetailLokasi()));
            array_push($d['sorot'], ...$this->ambil("bahaya sorot {$site}", $s->bahayaSorot()));
            array_push($d['temuan'], ...$this->ambil("temuan open {$site}", $s->temuanOpen()));
            array_push($d['statusBahaya'], ...$this->ambil("status per bahaya {$site} (Excel)", $s->statusPerBahaya()));
        }

        return $this->susun($d, $sampai, $cfg);
    }

    /**
     * Periksa semua query dengan EXPLAIN (sintaks, nama kolom, tipe) tanpa membaca data.
     * Query per site diperiksa memakai site pertama.
     *
     * @return array<string, string|null>  label => pesan error (null = lolos)
     */
    public function cek(CarbonImmutable $sampai): array
    {
        $sql = new DrmSql((array) config('peta_risiko_drm'), $sampai);
        $site = $this->ch->select($sql->daftarSite())[0]['site'] ?? null;
        $s = $sql->untukSite($site);
        $daftar = [
            'baseline HIRA' => $sql->hira(), 'detail lokasi' => $sql->detailLokasi(), 'master PJA' => $sql->pja(),
            'matriks per cakupan' => $sql->matriksCakupan(), 'insiden' => $sql->insiden(), 'personil PJA' => $sql->personil(),
            'BeARC mingguan' => $sql->bearc(), 'coverage' => $sql->coverage(), 'mapping SAP per bahaya' => $sql->mappingBahaya(),
            "aktivitas × detail lokasi ({$site})" => $s->aktivitasDetailLokasi(), "bahaya sorot ({$site})" => $s->bahayaSorot(),
            "temuan open ({$site})" => $s->temuanOpen(), "status per bahaya ({$site})" => $s->statusPerBahaya(),
        ];

        $hasil = [];
        foreach ($daftar as $label => $q) {
            try {
                $this->ch->select('EXPLAIN '.trim($q), 120);
                $hasil[$label] = null;
            } catch (\Throwable $e) {
                $hasil[$label] = $e->getMessage();
            }
        }

        return $hasil;
    }

    /**
     * Rakit snapshot dari baris mentah hasil query (terpisah dari pengambilan agar bisa diuji).
     *
     * @param  array<string, list<mixed>>  $d
     * @param  array<string, mixed>  $cfg
     * @return array{meta: array<string, mixed>, pja: array<string, array<string, mixed>>}
     */
    public function susun(array $d, CarbonImmutable $sampai, array $cfg): array
    {
        $sites = $d['sites'];
        $hira = $this->indeks($d['hira'], 'hid');
        $dl = $this->indeks($d['dl'], 'dl');
        $pja = $this->indeks($d['pja'], 'id');
        ['insiden' => $insiden, 'personil' => $personil, 'bearc' => $bearc, 'coverage' => $coverage,
            'akt' => $akt, 'sorot' => $sorot, 'temuan' => $temuan] = $d;

        $cakupan = $this->cakupan($d['matriks'], $sampai->toDateString());
        $pjaBc = $this->pjaBc($pja, $dl);

        $perDl = [
            'akt' => $this->kelompok($akt, 'dl'),
            'sorot' => $this->kelompok($sorot, 'dl'),
            'temuan' => $this->kelompok($temuan, 'dl'),
            'insiden' => $this->kelompok($insiden, 'dl'),
        ];
        $hiraPerA = $this->kelompok(array_values($hira), 'a');
        $coveragePerPja = $this->kelompok($coverage, 'pja');
        $bearcPerSid = $this->kelompok($bearc, 'sid');

        $berkas = [];
        foreach ($pjaBc as $id => $info) {
            $berkas[$id] = $this->berkasPja($info, $dl, $hiraPerA, $perDl, $personil, $bearcPerSid, $coveragePerPja[$id] ?? []);
        }

        return [
            'meta' => [
                'dibuat' => now()->toIso8601String(),
                'sampai' => $sampai->toDateString(),
                'jendela' => (int) $cfg['jendela_hari'],
                'stripHari' => (int) $cfg['strip_hari'],
                'stripMulai' => $sampai->subDays((int) $cfg['strip_hari'] - 1)->toDateString(),
                'ambangUlang' => (int) $cfg['ambang_berulang'],
                'kekerapan' => self::KEKERAPAN,
                'konsekuensi' => self::KONSEKUENSI,
                'level' => self::LEVEL,
                'sites' => $sites,
                'grup' => array_values(array_unique([...array_keys($cfg['grup_detail_lokasi']), 'Belum terpetakan'])),
                'grupDl' => $this->jumlahDlPerGrup($dl),
                'proses' => $this->daftarProses($cfg),
                'cakupan' => $cakupan,
                'pja' => array_values(array_map(fn ($p) => $this->ringkasPja($p, $cakupan, $berkas[$p['id']]), $pjaBc)),
                'kolomAkt' => self::KOLOM_AKT,
                'kolomBahaya' => self::KOLOM_BAHAYA,
            ],
            'pja' => $berkas,
            'ekspor' => $this->barisEkspor($hira, $d['mapping'] ?? [], $d['statusBahaya'] ?? [], $sorot, $cfg),
        ];
    }

    // ------------------------------------------------------------ cakupan

    /**
     * @param  list<array<string, mixed>>  $rows  baris matriksCakupan
     * @return array<string, array<string, mixed>>  kunci "SITE|LMO", "GRUP|Disposal|ALL", "PJA|123"
     */
    private function cakupan(array $rows, string $akhir): array
    {
        $hasil = [];
        foreach ($rows as $r) {
            $kunci = $r['jenis'].'|'.$r['kunci'];
            $hasil[$kunci] ??= ['tren' => [], 'kpi' => $this->kpiKosong(), 'mAwal' => $this->matriksKosong(), 'mFinal' => $this->matriksKosong()];
            $n = (int) $r['n'];
            $minggu = (string) $r['minggu'];

            $tren = $hasil[$kunci]['tren'][$minggu] ?? ['total' => 0, 'K' => 0, 'G' => 0, 'T' => 0, 'X' => 0, 'sig' => 0];
            $tren['total'] += $n;
            $tren[$r['status']] = ($tren[$r['status']] ?? 0) + $n;
            $tren['sig'] += $r['nf'] === 'Significant' ? $n : 0;
            $hasil[$kunci]['tren'][$minggu] = $tren;

            if ($minggu !== $akhir) {
                continue;
            }
            $kpi = &$hasil[$kunci]['kpi'];
            $kpi['total'] += $n;
            $kpi[$r['status']] += $n;
            $kpi['awal'][$r['n1']] = ($kpi['awal'][$r['n1']] ?? 0) + $n;
            $kpi['final'][$r['nf']] = ($kpi['final'][$r['nf']] ?? 0) + $n;
            if ($r['status'] === DrmSql::STATUS_TERKONTROL && $this->level($r['nf']) < $this->level($r['n1'])) {
                $kpi['turun'] += $n;
            }
            $kpi['laten'] += (int) ($r['laten'] ?? 0) === 1 ? $n : 0;
            unset($kpi);
            $this->isiSel($hasil[$kunci]['mAwal'], $r['k1'], $r['c1'], $n);
            $this->isiSel($hasil[$kunci]['mFinal'], $r['kf'], $r['cf'], $n);
        }

        foreach ($hasil as &$c) {
            ksort($c['tren']);
        }

        return $hasil;
    }

    /**
     * Satu baris per bahaya-risiko HIRA untuk Excel mapping. Status gabungan = terburuk antar
     * detail lokasi baseline (G > K > T > X), sama dengan cakupan SITE|ALL di dashboard.
     *
     * @param  array<string, array<string, mixed>>  $hira
     * @param  list<array<string, mixed>>  $mapping
     * @param  list<array<string, mixed>>  $statusBahaya  per site, dijumlahkan di sini
     * @param  list<array<string, mixed>>  $sorot
     * @param  array<string, mixed>  $cfg
     * @return list<array<string, mixed>>
     */
    private function barisEkspor(array $hira, array $mapping, array $statusBahaya, array $sorot, array $cfg): array
    {
        $map = $this->indeks($mapping, 'hid');
        $st = [];
        foreach ($statusBahaya as $r) {
            $a = &$st[(string) $r['hid']];
            foreach (['dl_n', 'g', 'k', 't', 'x', 'dl_open', 'dl_ulang', 'dl_stop', 'temuan_open', 'temuan_jendela'] as $kol) {
                $a[$kol] = ($a[$kol] ?? 0) + (int) $r[$kol];
            }
            $terakhir = $r['terakhir'] === '1970-01-01' ? '' : (string) $r['terakhir'];
            $a['terakhir'] = max($a['terakhir'] ?? '', $terakhir);
            unset($a);
        }
        $inc = [];
        foreach ($sorot as $r) {
            if ((int) $r['n_inc'] > 0) {
                $i = &$inc[(string) $r['hid']];
                $i['dl'] = ($i['dl'] ?? 0) + 1;
                $i['n'] = ($i['n'] ?? 0) + (int) $r['n_inc'];
                $i['tgl'] = max($i['tgl'] ?? '', (string) $r['tgl_inc']);
                unset($i);
            }
        }
        $namaProses = array_map(fn ($p) => $p[0], $cfg['proses_bisnis']) + ['X' => 'Belum terpetakan'];

        $hasil = [];
        foreach ($hira as $hid => $h) {
            $s = $st[$hid] ?? [];
            $status = match (true) {
                ($s['g'] ?? 0) > 0 => DrmSql::STATUS_GAP,
                ($s['k'] ?? 0) > 0 => DrmSql::STATUS_TERKONTROL,
                ($s['t'] ?? 0) > 0 => DrmSql::STATUS_TIDAK,
                ($s['x'] ?? 0) > 0 => DrmSql::STATUS_TIDAK_TERPETAKAN,
                default => '-',
            };
            $m = $map[$hid] ?? [];
            $i = $inc[$hid] ?? [];
            $hasil[] = [
                'hid' => (string) $hid, 'aktivitas' => $h['an'], 'sub' => $h['sn'], 'bahaya' => $h['bn'], 'potensi' => $h['p'],
                'sites' => implode(', ', (array) $h['sites']), 'grup' => implode(', ', (array) $h['grp']),
                'proses' => $namaProses[$h['proses'] ?? 'X'] ?? (string) ($h['proses'] ?? ''), 'faktor' => $h['faktor'],
                'n1' => $h['n1'], 'k1' => $h['k1'], 'c1' => $h['c1'], 'n2' => $h['n2'], 'k2' => $h['k2'], 'c2' => $h['c2'],
                'kontrol' => $h['kt'], 'status' => $status,
                'final' => $status === DrmSql::STATUS_TERKONTROL ? $h['n2'] : ($status === '-' ? '' : $h['n1']),
                'dl_n' => $s['dl_n'] ?? 0, 'dl_k' => $s['k'] ?? 0, 'dl_g' => $s['g'] ?? 0, 'dl_t' => $s['t'] ?? 0, 'dl_x' => $s['x'] ?? 0,
                'dl_open' => $s['dl_open'] ?? 0, 'dl_ulang' => $s['dl_ulang'] ?? 0, 'dl_stop' => $s['dl_stop'] ?? 0,
                'temuan_open' => $s['temuan_open'] ?? 0, 'temuan_jendela' => $s['temuan_jendela'] ?? 0, 'terakhir' => $s['terakhir'] ?? '',
                'n_a' => (int) ($m['n_a'] ?? 0), 'label_a' => implode(' | ', (array) ($m['label_a'] ?? [])),
                'n_b' => (int) ($m['n_b'] ?? 0), 'teks_b' => implode(' | ', (array) ($m['teks_b'] ?? [])),
                'inc_dl' => $i['dl'] ?? 0, 'inc_n' => $i['n'] ?? 0, 'inc_tgl' => $i['tgl'] ?? '',
                'laten' => ($i['dl'] ?? 0) > 0 && in_array($status, [DrmSql::STATUS_TIDAK, DrmSql::STATUS_TIDAK_TERPETAKAN], true),
            ];
        }
        usort($hasil, fn ($a, $b) => [$a['aktivitas'], $a['sub'], $a['bahaya']] <=> [$b['aktivitas'], $b['sub'], $b['bahaya']]);

        return $hasil;
    }

    /** @return array<string, mixed> */
    private function kpiKosong(): array
    {
        return ['total' => 0, 'K' => 0, 'G' => 0, 'T' => 0, 'X' => 0, 'turun' => 0, 'laten' => 0, 'awal' => [], 'final' => []];
    }

    /** @return list<list<int>> */
    private function matriksKosong(): array
    {
        return array_fill(0, 5, array_fill(0, 5, 0));
    }

    /** @param list<list<int>> $m */
    private function isiSel(array &$m, string $kekerapan, string $konsekuensi, int $n): void
    {
        $i = array_search($kekerapan, self::KEKERAPAN, true);
        $j = array_search($konsekuensi, self::KONSEKUENSI, true);
        if ($i !== false && $j !== false) {
            $m[$i][$j] += $n;
        }
    }

    private function level(string $nilai): int
    {
        $i = array_search($nilai, self::LEVEL, true);

        return $i === false ? -1 : $i;
    }

    /**
     * @param  array<string, array<string, mixed>>  $dl
     * @return array<string, array<string, int>>  [site|ALL][grup] => jumlah detail lokasi aktif
     */
    private function jumlahDlPerGrup(array $dl): array
    {
        $hasil = [];
        foreach ($dl as $d) {
            foreach (['ALL', $d['site']] as $s) {
                $hasil[$s][$d['grp']] = ($hasil[$s][$d['grp']] ?? 0) + 1;
            }
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return list<array{kode: string, nama: string, jenis: string}>
     */
    private function daftarProses(array $cfg): array
    {
        $hasil = [];
        foreach ($cfg['proses_bisnis'] as $kode => [$nama, $jenis]) {
            $hasil[] = ['kode' => (string) $kode, 'nama' => $nama, 'jenis' => $jenis];
        }
        $hasil[] = ['kode' => 'X', 'nama' => 'Belum terpetakan', 'jenis' => 'pendukung'];

        return $hasil;
    }

    // ---------------------------------------------------------------- PJA

    /**
     * PJA BC aktif yang punya detail lokasi, beserta anak Mitra-nya.
     *
     * @param  array<string, array<string, mixed>>  $pja
     * @param  array<string, array<string, mixed>>  $dl
     * @return array<string, array<string, mixed>>
     */
    private function pjaBc(array $pja, array $dl): array
    {
        $dlPerPja = [];
        foreach ($dl as $id => $d) {
            foreach ($d['pja'] as $p) {
                $dlPerPja[$p][] = (string) $id;
            }
        }
        $anak = [];
        foreach ($pja as $p) {
            if ($p['parent'] !== '0') {
                $anak[$p['parent']][] = $p['id'];
            }
        }

        $hasil = [];
        foreach ($pja as $id => $p) {
            if (empty($dlPerPja[$id])) {
                continue;
            }
            $sites = array_count_values(array_map(fn ($x) => $dl[$x]['site'], $dlPerPja[$id]));
            arsort($sites);
            $hasil[$id] = [
                'id' => $id,
                'nama' => $p['nama'],
                'tipe' => $p['tipe'],
                'site' => (string) array_key_first($sites),
                'dl' => $dlPerPja[$id],
                'mitra' => array_values(array_map(fn ($a) => ['id' => $a, 'nama' => $pja[$a]['nama']], $anak[$id] ?? [])),
            ];
        }
        uasort($hasil, fn ($a, $b) => [$a['site'], $a['nama']] <=> [$b['site'], $b['nama']]);

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $info
     * @param  array<string, array<string, mixed>>  $dl
     * @param  array<string, list<array<string, mixed>>>  $hiraPerA
     * @param  array<string, array<string, list<array<string, mixed>>>>  $perDl
     * @param  list<array<string, mixed>>  $personil
     * @param  array<string, list<array<string, mixed>>>  $bearcPerSid
     * @param  list<array<string, mixed>>  $coverage
     * @return array<string, mixed>
     */
    private function berkasPja(array $info, array $dl, array $hiraPerA, array $perDl, array $personil, array $bearcPerSid, array $coverage): array
    {
        $ambilDl = fn (string $jenis): array => array_merge(...array_map(fn ($id) => $perDl[$jenis][$id] ?? [], $info['dl']));
        $baris = $ambilDl('akt');
        $temuan = $ambilDl('temuan');
        usort($temuan, fn ($a, $b) => strcmp($b['tanggal'], $a['tanggal']));

        $bahaya = [];
        foreach (array_unique(array_map(fn ($r) => $r['a'], $baris)) as $a) {
            foreach ($hiraPerA[$a] ?? [] as $h) {
                $bahaya[] = [$h['hid'], $h['a'], $h['an'], $h['sn'], $h['bn'], $h['p'], $h['n1'], $h['k1'], $h['c1'], $h['n2'], $h['k2'], $h['c2'], $h['kt'], $h['faktor'], $h['proses'] ?? 'X'];
            }
        }

        // Personil = penugasan m_pja_emp pada PJA BC ini + PJA Mitra anaknya (dicocokkan lewat ID, bukan nama).
        $idPja = array_flip([(string) $info['id'], ...array_map('strval', array_column($info['mitra'], 'id'))]);
        $orang = [];
        foreach ($personil as $p) {
            if (isset($idPja[(string) $p['pja_id']])) {
                $orang[] = [...$p, 'bc' => (string) $p['pja_id'] === (string) $info['id']];
            }
        }
        $bearc = array_merge(...array_map(fn ($sid) => $bearcPerSid[$sid] ?? [], array_unique(array_column($orang, 'sid'))));
        $insiden = $ambilDl('insiden');
        usort($insiden, fn ($a, $b) => strcmp($b['tanggal'], $a['tanggal']));

        return [
            'info' => [...$info, 'dl' => null],
            'lokasi' => array_map(fn ($id) => [
                'dl' => $id, 'lokasi' => $dl[$id]['lokasi'], 'detlok' => $dl[$id]['detlok'], 'grp' => $dl[$id]['grp'], 'site' => $dl[$id]['site'],
            ], $info['dl']),
            'akt' => array_map(fn ($r) => [
                (string) $r['dl'], $r['a'], (int) $r['bahaya'], (int) $r['k'], (int) $r['g'], (int) $r['t'], (int) $r['x'],
                (int) $r['gap_open'], (int) $r['gap_ulang'], (int) $r['gap_stop'], (int) $r['sig'], (int) $r['high'],
                $r['terakhir'] === '1970-01-01' ? '' : (string) $r['terakhir'], (string) ($r['strip'] ?? ''), (int) $r['via_bukti'], (string) ($r['proses'] ?? 'X'),
            ], $baris),
            'namaAkt' => $this->namaAktivitas($baris),
            'bahaya' => $bahaya,
            'sorot' => $ambilDl('sorot'),
            'temuan' => array_slice($temuan, 0, self::TEMUAN_MAKS_PER_PJA),
            'temuanTotal' => count($temuan),
            'insiden' => $insiden,
            'personil' => $orang,
            'bearc' => $bearc,
            'coverage' => $coverage,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    private function kelompok(array $rows, string $kunci): array
    {
        $hasil = [];
        foreach ($rows as $r) {
            $hasil[(string) $r[$kunci]][] = $r;
        }

        return $hasil;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, string>  a (huruf kecil) => nama aktivitas tampilan
     */
    private function namaAktivitas(array $baris): array
    {
        $hasil = [];
        foreach ($baris as $r) {
            $hasil[$r['a']] ??= (string) $r['aktivitas'];
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $p
     * @param  array<string, array<string, mixed>>  $cakupan
     * @param  array<string, mixed>  $berkas
     * @return array<string, mixed>
     */
    private function ringkasPja(array $p, array $cakupan, array $berkas): array
    {
        $kpi = $cakupan['PJA|'.$p['id']]['kpi'] ?? $this->kpiKosong();
        $setahun = now()->subYear()->toDateString();

        return [
            'id' => $p['id'],
            'nama' => $p['nama'],
            'site' => $p['site'],
            'mitra' => count($p['mitra']),
            'dl' => count($p['dl']),
            'aktivitas' => count($berkas['namaAkt']),
            'kpi' => array_diff_key($kpi, ['awal' => 1, 'final' => 1]),
            'sig' => (int) ($kpi['final']['Significant'] ?? 0),
            'temuan' => $berkas['temuanTotal'],
            'insiden' => count(array_filter($berkas['insiden'], fn ($i) => $i['tanggal'] >= $setahun)),
            'personil' => count($berkas['personil']),
        ];
    }

    // ------------------------------------------------------------- utilitas

    /** @return list<array<string, mixed>> */
    private function ambil(string $label, string $sql): array
    {
        $mulai = microtime(true);
        $rows = $this->ch->select($sql, (int) config('peta_risiko_drm.timeout_detik', 900));
        ($this->log)(sprintf('%-45s %7s baris  %5.1f dtk', $label, number_format(count($rows), 0, ',', '.'), microtime(true) - $mulai));

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function indeks(array $rows, string $kunci): array
    {
        $hasil = [];
        foreach ($rows as $r) {
            $hasil[(string) $r[$kunci]] = $r;
        }

        return $hasil;
    }
}
