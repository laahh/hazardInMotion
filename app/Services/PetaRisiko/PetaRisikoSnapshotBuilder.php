<?php

namespace App\Services\PetaRisiko;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyusun snapshot Peta Risiko PJA dari dua bahan hasil analisis:
 * - workbook "Mapping_PJA_Proses_Lokasi_SAP.xlsx" (PJA → proses → lokasi → tema → bukti SAP)
 * - HTML "Peta_HIRA_Risiko_Dinamis" (master proses, tema, bahaya HIRA + pengendalian + Layer SBS)
 *
 * Hasil: ['meta' => [...], 'pja' => [id => [...]]]. Disimpan sebagai JSON oleh
 * PetaRisikoRepository. Baris dibuat ringkas (array posisi) agar file per PJA kecil.
 */
class PetaRisikoSnapshotBuilder
{
    /** Posisi kolom baris tema di snapshot (dipakai juga oleh JS). */
    public const KOLOM = [
        'proses', 'klaster', 'lokasi', 'detail', 'tema', 'awal', 'residual', 'efektif',
        'status', 'alasan', 'bahaya', 'temuan', 'open', 'lewat', 'openHigh', 'pjaLain',
    ];

    /** Posisi kolom temuan SAP open. */
    public const KOLOM_SAP = [
        'id', 'tanggal', 'jenis', 'lokasi', 'detail', 'proses', 'tema',
        'ketidaksesuaian', 'sub', 'status', 'nilai', 'janji', 'lewat',
    ];

    private const SHEET = ['wk' => 'Mapping Tema', 'ytd' => 'Mapping Tema YTD'];

    /** @var array<string, string> nama proses → kode */
    private array $kodeProses = [];

    /** @var array<string, string> nama tema → kode */
    private array $kodeTema = [];

    /** @var array<string, array<string, array<string, mixed>>> [proses][bahaya lower] => bahaya HIRA */
    private array $hira = [];

    /** @var array<string, string> "site|nama PJA" → id */
    private array $idPja = [];

    /** @return array{meta: array<string, mixed>, pja: array<string, array<string, mixed>>} */
    public function bangun(string $xlsxPath, string $htmlPath): array
    {
        $html = $this->bacaFile($htmlPath);
        $proses = $this->prosesDariHtml($html);
        $tema = $this->jsonBlok($html, 'dynw-data')['themes'] ?? [];
        $sbs = $this->jsonBlok($html, 'sbs-data')['layers'] ?? [];
        $hira = $this->hira = $this->hiraDariHtml($html);

        if ($proses === [] || $tema === []) {
            throw new RuntimeException('Master proses/tema tidak ditemukan di HTML Peta HIRA.');
        }

        foreach ($proses as $p) {
            $this->kodeProses[mb_strtolower($p['n'])] = $p['c'];
        }
        foreach ($tema as [$kode, $nama]) {
            $this->kodeTema[mb_strtolower($nama)] = $kode;
        }

        $xlsx = new XlsxStreamReader($xlsxPath);
        $info = $this->info($xlsx);

        $pja = [];
        foreach (self::SHEET as $periode => $sheet) {
            foreach ($xlsx->rows($sheet) as $r) {
                $id = $this->pjaId($pja, $r['Site'], $r['PJA']);
                $pja[$id]['periode'][$periode]['rows'][] = $this->barisTema($r);
            }
        }
        foreach ($xlsx->rows('SAP Open') as $r) {
            $id = $this->pjaId($pja, $r['Site'], $r['PJA']);
            $pja[$id]['open'][] = $this->barisSap($r);
        }

        $daftar = [];
        foreach ($pja as $id => &$p) {
            $p['hira'] = $this->hiraUntuk($p, $hira);
            $ringkas = [];
            foreach (array_keys(self::SHEET) as $periode) {
                $ringkas[$periode] = self::ringkas($p['periode'][$periode]['rows'] ?? [], $p['open'] ?? []);
            }
            $daftar[] = ['id' => $id, 'nama' => $p['nama'], 'site' => $p['site'], 'ringkas' => $ringkas];
        }
        unset($p);

        usort($daftar, fn ($a, $b) => [$a['site'], $a['nama']] <=> [$b['site'], $b['nama']]);

        return [
            'meta' => [
                'dibuat' => now()->toIso8601String(),
                'periode' => self::labelPeriode($info['Periode utama'] ?? ''),
                'catatan' => array_values(array_filter($info, fn ($k) => str_starts_with($k, 'Peringatan'), ARRAY_FILTER_USE_KEY)),
                'proses' => $proses,
                'tema' => $tema,
                'sbs' => array_map(fn ($l) => ['id' => $l['id'], 'nama' => $l['name'], 'tanya' => $l['q'] ?? ''], $sbs),
                'kolom' => self::KOLOM,
                'kolomSap' => self::KOLOM_SAP,
                'pja' => $daftar,
            ],
            'pja' => $pja,
        ];
    }

    /**
     * Ringkasan per PJA: unit = kombinasi (proses, tema) dengan status terburuk antar lokasi.
     *
     * @param  list<array<int, mixed>>  $rows
     * @param  list<array<int, mixed>>  $open
     * @return array<string, int>
     */
    public static function ringkas(array $rows, array $open): array
    {
        $iP = array_search('proses', self::KOLOM, true);
        $iT = array_search('tema', self::KOLOM, true);
        $iS = array_search('status', self::KOLOM, true);
        $iL = array_search('lewat', array_values(self::KOLOM_SAP), true);

        $unit = [];
        $proses = [];
        foreach ($rows as $r) {
            $k = $r[$iP].'|'.$r[$iT];
            $unit[$k] = isset($unit[$k]) ? PetaRisikoStatus::terburuk($unit[$k], $r[$iS]) : $r[$iS];
            $proses[$r[$iP]] = true;
        }

        $hasil = [PetaRisikoStatus::GAP => 0, PetaRisikoStatus::TINDAK => 0, PetaRisikoStatus::FYI => 0, PetaRisikoStatus::OK => 0];
        foreach ($unit as $s) {
            $hasil[$s]++;
        }

        return $hasil + [
            'unit' => count($unit),
            'proses' => count($proses),
            'open' => count($open),
            'lewat' => count(array_filter($open, fn ($o) => (bool) $o[$iL])),
        ];
    }

    /**
     * "Minggu 1-7 Nov 2026 (status per 8 Nov 2026). Sheet berakhiran YTD = Jan-29 Sep 2026"
     * → ['wk' => 'Minggu 1-7 Nov 2026', 'ytd' => 'Jan-29 Sep 2026', 'status' => 'status per 8 Nov 2026'].
     *
     * @return array{wk:string, ytd:string, status:string}
     */
    public static function labelPeriode(string $teks): array
    {
        if (! preg_match('/^\s*([^(]+?)\s*\(([^)]*)\)/u', $teks, $wk)) {
            preg_match('/^\s*([^.]+)/u', $teks, $wk);
        }
        preg_match('/YTD\s*=\s*(.+?)\s*$/u', $teks, $ytd);

        return [
            'wk' => trim($wk[1] ?? '') ?: 'Minggu terakhir',
            'ytd' => 'YTD '.(trim($ytd[1] ?? '') ?: ''),
            'status' => trim($wk[2] ?? ''),
        ];
    }

    /** @return array<int, mixed> */
    private function barisTema(array $r): array
    {
        $proses = $this->proses($r['Bisnis proses'] ?? '');
        $bahaya = array_values(array_filter(array_map('trim', explode('|', $r['Bahaya HIRA terkait'] ?? ''))));
        $adaKontrol = false;
        foreach ($bahaya as $b) {
            $adaKontrol = $adaKontrol || ! empty($this->hira[$proses][mb_strtolower($b)]['c']);
        }
        [$status, $alasan] = PetaRisikoStatus::dari($r['Status bukti SAP'] ?? '', $r['Padanan HIRA langsung'] ?? '', $adaKontrol);

        return [
            $proses,
            $r['Klaster lokasi'] ?? '',
            $r['Lokasi'] ?? '',
            $r['Detail lokasi (master)'] ?? '',
            $this->tema($r['Tema bahaya'] ?? ''),
            PetaRisikoStatus::level($r['Risiko awal (HIRA proses)'] ?? ''),
            PetaRisikoStatus::level($r['Risiko residual (HIRA proses)'] ?? ''),
            PetaRisikoStatus::level($r['Risiko efektif'] ?? ''),
            $status,
            $alasan,
            $bahaya,
            (int) ($r['Temuan SAP periode'] ?? 0),
            (int) ($r['SAP open'] ?? 0),
            (int) ($r['Open lewat janji'] ?? 0),
            (int) ($r['Open High+'] ?? 0),
            $r['PJA lain di lokasi'] ?? '',
        ];
    }

    /** @return array<int, mixed> */
    private function barisSap(array $r): array
    {
        return [
            $r['ID laporan'] ?? '',
            $r['Tanggal'] ?? '',
            $r['Jenis'] ?? '',
            $r['Lokasi'] ?? '',
            $r['Detail lokasi (SAP)'] ?? '',
            array_values(array_unique(array_map(
                fn ($n) => $this->proses($n),
                array_filter(array_map('trim', explode('|', $r['Bisnis proses lokasi'] ?? '')))
            ))),
            $this->tema($r['Tema'] ?? ''),
            $r['Ketidaksesuaian'] ?? '',
            $r['Sub-ketidaksesuaian'] ?? '',
            $r['Status'] ?? '',
            PetaRisikoStatus::level($r['Nilai risiko'] ?? ''),
            $r['Janji selesai'] ?? '',
            strtolower($r['Lewat janji'] ?? '') === 'ya' ? 1 : 0,
        ];
    }

    private function pjaId(array &$pja, string $site, string $nama): string
    {
        $kunci = $site.'|'.$nama;
        if (isset($this->idPja[$kunci])) {
            return $this->idPja[$kunci];
        }

        $id = Str::slug($nama) ?: 'pja';
        if (isset($pja[$id])) {
            $id = Str::slug($nama.' '.$site);
        }
        $pja[$id] = ['id' => $id, 'nama' => $nama, 'site' => $site, 'periode' => [], 'open' => []];

        return $this->idPja[$kunci] = $id;
    }

    private function proses(string $nama): string
    {
        return $this->kodeProses[mb_strtolower(trim($nama))] ?? 'X';
    }

    private function tema(string $nama): string
    {
        return $this->kodeTema[mb_strtolower(trim($nama))] ?? Str::upper(Str::substr(Str::slug($nama, ''), 0, 3));
    }

    /**
     * Bahaya HIRA + pengendalian untuk proses yang disentuh PJA, hanya bahaya yang dirujuk barisnya.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $hira  [proses][bahaya lower] => data
     * @return array<string, list<array<string, mixed>>>
     */
    private function hiraUntuk(array $pja, array $hira): array
    {
        $iP = array_search('proses', self::KOLOM, true);
        $iB = array_search('bahaya', self::KOLOM, true);
        $hasil = [];
        foreach ($pja['periode'] as $data) {
            foreach ($data['rows'] ?? [] as $r) {
                foreach ($r[$iB] as $b) {
                    $kunci = mb_strtolower($b);
                    if (isset($hira[$r[$iP]][$kunci])) {
                        $hasil[$r[$iP]][$kunci] = $hira[$r[$iP]][$kunci];
                    }
                }
            }
        }

        return array_map('array_values', $hasil);
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private function hiraDariHtml(string $html): array
    {
        $hasil = [];
        foreach ($this->jsonBlok($html, 'hz-data') as $proses => $daftar) {
            foreach ($daftar as $h) {
                $kontrol = array_map(fn ($c) => [
                    't' => Str::ucfirst($c['t'] ?? ''),
                    'tp' => $c['tp'] ?? '',
                    'k' => $c['k'] ?? '',
                    's' => $c['s'] ?? '',
                    'n' => (int) ($c['n'] ?? 0),
                ], $h['cs'] ?? []);
                $hasil[$proses][mb_strtolower(trim($h['b']))] = [
                    'b' => trim($h['b']),
                    'p' => $h['p'] ?? '',
                    'a' => (int) ($h['a'] ?? 0),
                    'r' => (int) ($h['r'] ?? 0),
                    'n' => (int) ($h['n'] ?? 0),
                    'c' => $kontrol,
                ];
            }
        }

        return $hasil;
    }

    /** @return list<array{c:string, n:string, g:string, d:string}> */
    private function prosesDariHtml(string $html): array
    {
        $pola = "/\\{c:'((?:\\\\.|[^'\\\\])*)',n:'((?:\\\\.|[^'\\\\])*)',g:'((?:\\\\.|[^'\\\\])*)',d:'((?:\\\\.|[^'\\\\])*)'\\}/";
        preg_match_all($pola, $html, $m, PREG_SET_ORDER);

        $hasil = [];
        foreach ($m as [, $c, $n, $g, $d]) {
            $hasil[$c] = ['c' => $c, 'n' => stripslashes($n), 'g' => $g, 'd' => stripslashes($d)];
        }

        return array_values($hasil);
    }

    /** @return array<string, mixed> */
    private function jsonBlok(string $html, string $id): array
    {
        // strpos, bukan regex: blok bisa >1 MB dan melampaui pcre.backtrack_limit.
        $tag = strpos($html, 'id="'.$id.'"');
        $awal = $tag === false ? false : strpos($html, '>', $tag);
        $akhir = $awal === false ? false : strpos($html, '</script>', $awal);
        if ($akhir === false) {
            throw new RuntimeException("Blok data '{$id}' tidak ditemukan di HTML Peta HIRA.");
        }

        return json_decode(substr($html, $awal + 1, $akhir - $awal - 1), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private function info(XlsxStreamReader $xlsx): array
    {
        $info = [];
        foreach ($xlsx->rows('Info') as $r) {
            $info[$r['Isi'] ?? ''] = $r['Keterangan'] ?? '';
        }

        return $info;
    }

    private function bacaFile(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("File tidak ditemukan: {$path}");
        }

        return (string) file_get_contents($path);
    }
}
