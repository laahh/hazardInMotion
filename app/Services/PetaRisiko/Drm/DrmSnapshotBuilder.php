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
            'akt' => [], 'sorot' => [], 'temuan' => [],
        ];
        foreach ($d['sites'] as $site) {
            $s = $sql->untukSite($site);
            array_push($d['akt'], ...$this->ambil("aktivitas × detail lokasi {$site}", $s->aktivitasDetailLokasi()));
            array_push($d['sorot'], ...$this->ambil("bahaya sorot {$site}", $s->bahayaSorot()));
            array_push($d['temuan'], ...$this->ambil("temuan open {$site}", $s->temuanOpen()));
        }

        return $this->susun($d, $sampai, $cfg);
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
        $this->tambahLaten($cakupan, $sorot, $dl, $hira);
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
     * Risiko laten = bahaya unik yang terkait insiden 12 bulan di cakupan itu tapi kini tidak teridentifikasi SAP.
     *
     * @param  array<string, array<string, mixed>>  $cakupan
     * @param  list<array<string, mixed>>  $sorot
     * @param  array<string, array<string, mixed>>  $dl
     * @param  array<string, array<string, mixed>>  $hira
     */
    private function tambahLaten(array &$cakupan, array $sorot, array $dl, array $hira): void
    {
        $laten = [];
        foreach ($sorot as $r) {
            if ((int) $r['n_inc'] === 0 || ! in_array($r['status'], [DrmSql::STATUS_TIDAK, DrmSql::STATUS_TIDAK_TERPETAKAN], true)) {
                continue;
            }
            $d = $dl[(string) $r['dl']] ?? null;
            if ($d === null) {
                continue;
            }
            $kunci = ['SITE|ALL', 'SITE|'.$d['site'], 'GRUP|'.$d['grp'].'|ALL', 'GRUP|'.$d['grp'].'|'.$d['site']];
            $proses = $hira[$r['hid']]['proses'] ?? null;
            if ($proses !== null) {
                array_push($kunci, 'PROSES|'.$proses.'|ALL', 'PROSES|'.$proses.'|'.$d['site']);
            }
            foreach ($d['pja'] as $p) {
                $kunci[] = 'PJA|'.$p;
            }
            foreach ($kunci as $k) {
                $laten[$k][$r['hid']] = true;
            }
        }
        foreach ($cakupan as $k => &$c) {
            $c['kpi']['laten'] = count($laten[$k] ?? []);
        }
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

        $namaBc = $info['nama'];
        $namaMitra = array_flip(array_column($info['mitra'], 'nama'));
        $orang = array_values(array_filter($personil, fn ($p) => $p['pja'] === $namaBc || isset($namaMitra[$p['pja']]) || $p['parent'] === $namaBc));
        $bearc = array_merge(...array_map(fn ($p) => $bearcPerSid[$p['sid']] ?? [], $orang));
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
