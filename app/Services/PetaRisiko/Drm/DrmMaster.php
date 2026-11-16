<?php

namespace App\Services\PetaRisiko\Drm;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Master mapping DRM 2.1 dari workbook "DRM21_Mapping_Master_Overall.xlsx".
 *
 * Bagian kurasi yang diambil dari workbook (tidak bisa dihitung ulang dari ClickHouse):
 *   M01 baris HIRA v31 (kunci id_baris = ri), M04 grup lokasi, M05 grup → baris, M06 detail lokasi → grup,
 *   M09 label Hazard/Inspeksi → baris, M11 teks aktivitas OBS → baris, M12 teks aktivitas OAK → baris.
 * Disimpan sebagai JSON di storage/app/{config master.dir}/master.json; xlsx aslinya ikut disalin.
 *
 * Supaya query ClickHouse tetap di bawah max_query_size (256 KB), teks tidak dikirim: nama detail lokasi,
 * label, teks aktivitas, dan teks bahaya diwakili CRC32 dari teks yang dinormalisasi (norm()).
 * ClickHouse menghitung hal yang sama: CRC32(trimBoth(replaceRegexpAll(lowerUTF8(x), '\s+', ' '))).
 */
final class DrmMaster
{
    private const NAMA_JSON = 'master.json';

    private const NAMA_XLSX = 'DRM21_Mapping_Master.xlsx';

    private const TIPE_PETA = 'Array(Tuple(UInt32, Array(UInt32)))';

    /** Kode kategori bahaya di literal: urutan = nilai. */
    public const KB = ['', 'Low', 'Medium', 'High', 'Significant'];

    /** Sheet yang dibaca dan judul kolom pertama baris ke-2 (penjaga format). */
    private const SHEET = [
        'M01_Baris_HIRA' => 'id_baris (ri)',
        'M04_Grup_Lokasi' => 'id_grup',
        'M05_Bridge_Grup_Baris' => 'id_grup',
        'M06_DetailLokasi' => 'id_DL',
        'M09_Label_SAP_Baris' => 'Label SAP (ketidaksesuaian / subketidaksesuaian)',
        'M11_OBS_Aktivitas_Baris' => 'Teks aktivitas OBS (persis)',
        'M12_OAK_Aktivitas_Baris' => 'Teks aktivitas OAK (persis)',
    ];

    /**
     * @param  array{
     *   dibuat: string, sumber: string,
     *   baris: list<array{ri: int, aid: int, aktivitas: string, sub: string, bahaya: string, kb: string, kelompokM01: string, umum: bool, idh: list<int>}>,
     *   grup: array<int, string>, grupBaris: array<int, list<int>>,
     *   dl: list<array{id: int, site: string, lokasi: string, detail: string, gid: int}>,
     *   label: list<array{k: string, sk: string, ri: list<int>, sumber: string}>,
     *   obs: list<array{teks: string, ri: list<int>}>, oak: list<array{teks: string, ri: list<int>}>
     * }  $data
     */
    private function __construct(public readonly array $data)
    {
    }

    /**
     * Master dari array berstruktur sama dengan master.json (dipakai uji dan alat bantu).
     *
     * @param  array<string, mixed>  $data
     */
    public static function dariArray(array $data): self
    {
        return new self($data + ['dibuat' => now()->toIso8601String(), 'sumber' => '-', 'baris' => [], 'grup' => [], 'grupBaris' => [],
            'dl' => [], 'label' => [], 'obs' => [], 'oak' => []]);
    }

    public static function dir(): string
    {
        return (string) config('peta_risiko_drm.master.dir', 'peta-risiko-drm-master');
    }

    public static function ada(): bool
    {
        return Storage::disk('local')->exists(self::dir().'/'.self::NAMA_JSON);
    }

    /**
     * Master yang dipakai sync: tabel database drm_master_* bila sudah dimigrasi dan terisi (bisa dikoreksi di
     * /peta-risiko-pja/master), selain itu master.json hasil impor workbook.
     *
     * @throws RuntimeException bila keduanya belum ada
     */
    public static function muat(): self
    {
        if (Master\DrmMasterDb::terisi()) {
            return new self(app(Master\DrmMasterDb::class)->data());
        }

        return self::muatJson();
    }

    /** Master dari master.json (hasil impor workbook), tanpa koreksi database. */
    public static function muatJson(): self
    {
        if (! self::ada()) {
            throw new RuntimeException('Master mapping DRM belum diimpor. Jalankan: php artisan peta-risiko:impor-master <file.xlsx>');
        }
        $data = json_decode((string) Storage::disk('local')->get(self::dir().'/'.self::NAMA_JSON), true, 512, JSON_THROW_ON_ERROR);

        return new self($data);
    }

    public static function pathXlsx(): string
    {
        return Storage::disk('local')->path(self::dir().'/'.self::NAMA_XLSX);
    }

    /** Baca workbook master. @throws RuntimeException bila sheet/kolom tidak sesuai format */
    public static function dariXlsx(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("File tidak ditemukan: {$path}");
        }
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(array_keys(self::SHEET));
        $book = $reader->load($path);

        $sheet = [];
        foreach (self::SHEET as $nama => $judul) {
            $ws = $book->getSheetByName($nama) ?? throw new RuntimeException("Sheet {$nama} tidak ada di workbook.");
            $rows = $ws->toArray(null, false, false, false);
            if (trim((string) ($rows[1][0] ?? '')) !== $judul) {
                throw new RuntimeException("Sheet {$nama}: kolom A baris 2 harus \"{$judul}\".");
            }
            $sheet[$nama] = array_values(array_filter(array_slice($rows, 2), fn ($r) => trim((string) ($r[0] ?? '')) !== ''));
        }
        $book->disconnectWorksheets();

        return new self([
            'dibuat' => now()->toIso8601String(),
            'sumber' => basename($path),
            'baris' => array_map(fn ($r) => [
                'ri' => (int) $r[0], 'aid' => (int) $r[1], 'aktivitas' => trim((string) $r[2]), 'sub' => trim((string) $r[3]),
                'bahaya' => trim((string) $r[4]), 'kb' => trim((string) $r[5]), 'kelompokM01' => trim((string) $r[6]),
                'umum' => strtoupper(trim((string) $r[7])) === 'Y', 'idh' => self::angka((string) $r[8]),
            ], $sheet['M01_Baris_HIRA']),
            'grup' => array_column(array_map(fn ($r) => [(int) $r[0], trim((string) $r[1])], $sheet['M04_Grup_Lokasi']), 1, 0),
            'grupBaris' => self::kelompokkan(array_map(fn ($r) => [(int) $r[0], (int) $r[2]], $sheet['M05_Bridge_Grup_Baris'])),
            'dl' => array_map(fn ($r) => [
                'id' => (int) $r[0], 'site' => trim((string) $r[1]), 'lokasi' => trim((string) $r[2]),
                'detail' => trim((string) $r[3]), 'gid' => (int) $r[4],
            ], $sheet['M06_DetailLokasi']),
            'label' => self::label($sheet['M09_Label_SAP_Baris']),
            'obs' => self::teks($sheet['M11_OBS_Aktivitas_Baris']),
            'oak' => self::teks($sheet['M12_OAK_Aktivitas_Baris']),
        ]);
    }

    public function simpan(?string $salinXlsxDari = null): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::dir());
        $disk->put(self::dir().'/'.self::NAMA_JSON, json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if ($salinXlsxDari !== null && realpath($salinXlsxDari) !== realpath(self::pathXlsx())) {
            copy($salinXlsxDari, self::pathXlsx());
        }
        @chmod($disk->path(self::dir()), 0755);
        foreach ([self::NAMA_JSON, self::NAMA_XLSX] as $f) {
            @chmod($disk->path(self::dir().'/'.$f), 0644);
        }
    }

    // ------------------------------------------------------------ turunan

    /** Normalisasi teks yang sama dengan sisi ClickHouse (lihat DrmSql::norm). */
    public static function norm(string $s): string
    {
        return trim((string) preg_replace('/[ \t\n\r\f]+/', ' ', mb_strtolower($s, 'UTF-8')), ' ');
    }

    public static function crc(string $s): int
    {
        return crc32(self::norm($s));
    }

    /** Kunci lokasi: CRC32("site|lokasi" ternormalisasi), sama dengan dl.lk di DrmSql. */
    public static function crcLokasi(string $site, string $lokasi): int
    {
        return crc32(self::norm($site).'|'.self::norm($lokasi));
    }

    /** Sumber master: database (bisa dikoreksi) atau master.json. */
    public function dariDatabase(): bool
    {
        return isset($this->data['kelompokAkt']);
    }

    /**
     * Config efektif: daftar kelompok aktivitas + regex dan tema checklist + regex dari database bila ada
     * (koreksi di halaman master), selain itu dari config/peta_risiko_drm.php.
     *
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    public function cfgEfektif(array $cfg): array
    {
        if (! empty($this->data['kelompokDaftar'])) {
            $cfg['proses_bisnis'] = $this->data['kelompokDaftar'];
        }
        if (! empty($this->data['tema'])) {
            $cfg['tema_checklist'] = $this->data['tema'];
        }

        return $cfg;
    }

    /** Kunci detail lokasi: CRC32("site|lokasi|detail" ternormalisasi), sama dengan DrmSql. */
    public static function crcDl(string $site, string $lokasi, string $detail): int
    {
        return crc32(self::norm($site).'|'.self::norm($lokasi).'|'.self::norm($detail));
    }

    /**
     * Kelompok aktivitas (M02) per baris dari regex config proses_bisnis atas teks AKTIVITAS saja
     * (M03 = bridge per aktivitas). Uji 16 Nov 2026: 1.723 dari 1.726 baris sama dengan kolom G M01;
     * memakai "aktivitas sub" justru berbeda 273 baris.
     *
     * @param  array<string, array{0: string, 1: string, 2: string}>  $kelompok
     * @return array<int, string>  ri => kode (K0..K25)
     */
    public function kelompokBaris(array $kelompok): array
    {
        if (isset($this->data['kelompokAkt'])) {
            // Koreksi manual di database (tab Aktivitas → kelompok): kelompok mengikuti aktivitas baris.
            return array_column(array_map(fn ($b) => [$b['ri'], (string) ($this->data['kelompokAkt'][$b['aid']] ?? 'K0')], $this->data['baris']), 1, 0);
        }
        $hasil = [];
        foreach ($this->data['baris'] as $b) {
            $hasil[$b['ri']] = self::kelompokTeks($b['aktivitas'], $kelompok);
        }

        return $hasil;
    }

    /** @param array<string, array{0: string, 1: string, 2: string}> $kelompok */
    public static function kelompokTeks(string $teks, array $kelompok): string
    {
        foreach ($kelompok as $kode => [, , $pola]) {
            if ($pola !== '' && preg_match('~'.$pola.'~iu', $teks) === 1) {
                return (string) $kode;
            }
        }

        return 'K0';
    }

    /**
     * M16: tema → baris yang teks "bahaya sub " cocok regex tema (kolom 2 config tema_checklist).
     * Spasi penutup sengaja: pola seperti "ban " / "rem " harus bisa cocok di akhir teks. Uji 16 Nov
     * 2026: persis 1.696 pasangan, sama per tema dengan M15/M16 workbook.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $tema
     * @return array<int, list<int>>
     */
    public function temaBaris(array $tema): array
    {
        if (isset($this->data['temaBarisDb'])) {
            return $this->data['temaBarisDb'];
        }
        $hasil = [];
        foreach ($tema as $id => [, , $pola]) {
            foreach ($this->data['baris'] as $b) {
                if (preg_match('~'.$pola.'~iu', $b['bahaya'].' '.$b['sub'].' ') === 1) {
                    $hasil[(int) $id][] = $b['ri'];
                }
            }
        }

        return $hasil;
    }

    /** @return array<int, array<string, mixed>> ri => baris */
    public function barisPerRi(): array
    {
        return array_column($this->data['baris'], null, 'ri');
    }

    /**
     * Literal SQL ringkas untuk prelude DrmSql (semua angka; teks diwakili CRC32).
     * Tiap master dikirim sebagai SATU string JSON yang diurai JSONExtract di ClickHouse: literal array
     * biasa dihitung satu simpul per angka dan disalin ke setiap CTE pemakainya, sehingga query matriks
     * melewati batas "Query tree is too big" (500.000 simpul) — sync 16 Nov 2026.
     *   mh : [(ri, [id_hira_master], crc bahaya, id_aktivitas, kelompok, kb, umum)]
     *   mg : [(id_grup, [ri])]                       M05
     *   m6 : [(crcDl site|lokasi|detail, id_grup)]   M06
     *   m9 : [(crc ket / subket, [ri])]              M09
     *   mo : [(crc teks aktivitas, [ri])]            M11 + M12
     *   mt : [(id_tema, [ri])]                       M16
     *   gn : nama grup, indeks = id_grup
     *
     * @param  array<string, mixed>  $cfg  config peta_risiko_drm
     * @return array{mh: string, mg: string, m6: string, m9: string, mo: string, mt: string, gn: string}
     */
    public function literal(array $cfg): array
    {
        $kel = $this->kelompokBaris($cfg['proses_bisnis']);
        $mh = array_map(fn ($b) => sprintf(
            '[%d,[%s],%d,%d,%d,%d,%d]',
            $b['ri'], implode(',', $b['idh']), self::crc($b['bahaya']), $b['aid'],
            (int) substr($kel[$b['ri']], 1), max(0, (int) array_search($b['kb'], self::KB, true)), (int) $b['umum'],
        ), $this->data['baris']);

        $mg = [];
        foreach ($this->data['grupBaris'] as $gid => $ri) {
            $mg[] = sprintf('[%d,[%s]]', $gid, implode(',', $ri));
        }

        $m6 = [];
        foreach ($this->data['dl'] as $d) {
            $c = self::crcDl($d['site'], $d['lokasi'], $d['detail']);
            $m6[$c] = sprintf('[%d,%d]', $c, $d['gid']);
        }

        $m9 = [];
        foreach ($this->data['label'] as $l) {
            $m9[self::crc(self::norm($l['k']).' / '.self::norm($l['sk']))] = $l['ri'];
        }

        $mo = [];
        foreach ([...$this->data['obs'], ...$this->data['oak']] as $t) {
            $c = self::crc($t['teks']);
            $mo[$c] = array_values(array_unique([...($mo[$c] ?? []), ...$t['ri']]));
        }

        $gn = [];
        foreach ($this->data['grup'] as $gid => $nama) {
            $gn[(int) $gid] = $nama;
        }
        $maks = $gn === [] ? 0 : max(array_keys($gn));
        $namaGrup = array_map(fn ($i) => $gn[$i] ?? '', range(1, max(1, $maks)));

        $lit = [
            'mh' => self::json('['.implode(',', $mh).']', 'Array(Tuple(UInt32, Array(Int64), UInt32, UInt32, UInt8, UInt8, UInt8))'),
            'mg' => self::json('['.implode(',', $mg).']', self::TIPE_PETA),
            'm6' => self::json('['.implode(',', $m6).']', 'Array(Tuple(UInt32, UInt32))'),
            'm9' => self::json(self::petaLarik($m9), self::TIPE_PETA),
            'mo' => self::json(self::petaLarik($mo), self::TIPE_PETA),
            'mt' => self::json(self::petaLarik($this->temaBaris($cfg['tema_checklist'])), self::TIPE_PETA),
            'gn' => '['.implode(',', array_map(fn ($n) => "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $n)."'", $namaGrup)).']',
        ];
        // Lokasi → PJA dari database (koreksi manual): [(crc site|lokasi, crc nama PJA)]. Tanpa ini DrmSql membaca aaj_lokasi_dan_pja.
        if (isset($this->data['lokasiPja'])) {
            $lp = [];
            foreach ($this->data['lokasiPja'] as [$site, $lokasi, $pja]) {
                $lp[self::crcLokasi($site, $lokasi).'|'.self::crc($pja)] = sprintf('[%d,%d]', self::crcLokasi($site, $lokasi), self::crc($pja));
            }
            $lit['lp'] = self::json('['.implode(',', $lp).']', 'Array(Tuple(UInt32, UInt32))');
        }

        return $lit;
    }

    // ------------------------------------------------------------ util

    /** @return list<int> */
    private static function angka(string $s): array
    {
        preg_match_all('/\d+/', $s, $m);

        return array_map('intval', $m[0]);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $pasangan
     * @return array<int, list<int>>
     */
    private static function kelompokkan(array $pasangan): array
    {
        $hasil = [];
        foreach ($pasangan as [$k, $v]) {
            $hasil[$k][] = $v;
        }

        return $hasil;
    }

    /**
     * M09 dikelompokkan per (ketidaksesuaian, subketidaksesuaian).
     *
     * @param  list<list<mixed>>  $rows
     * @return list<array{k: string, sk: string, ri: list<int>, sumber: string}>
     */
    private static function label(array $rows): array
    {
        $hasil = [];
        foreach ($rows as $r) {
            $k = trim((string) $r[1]);
            $sk = trim((string) $r[2]);
            if ($r[3] === null || $r[3] === '') {
                continue;
            }
            $kunci = self::norm($k).' / '.self::norm($sk);
            $hasil[$kunci] ??= ['k' => $k, 'sk' => $sk, 'ri' => [], 'sumber' => trim((string) ($r[5] ?? ''))];
            $hasil[$kunci]['ri'][] = (int) $r[3];
        }

        return array_values(array_map(fn ($l) => [...$l, 'ri' => array_values(array_unique($l['ri']))], $hasil));
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<array{teks: string, ri: list<int>}>
     */
    private static function teks(array $rows): array
    {
        $hasil = [];
        foreach ($rows as $r) {
            if ($r[1] === null || $r[1] === '') {
                continue;
            }
            $kunci = self::norm((string) $r[0]);
            $hasil[$kunci] ??= ['teks' => trim((string) $r[0]), 'ri' => []];
            $hasil[$kunci]['ri'][] = (int) $r[1];
        }

        return array_values(array_map(fn ($t) => [...$t, 'ri' => array_values(array_unique($t['ri']))], $hasil));
    }

    /** @param array<int, list<int>> $peta */
    private static function petaLarik(array $peta): string
    {
        $isi = [];
        foreach ($peta as $k => $v) {
            $isi[] = sprintf('[%d,[%s]]', $k, implode(',', $v));
        }

        return '['.implode(',', $isi).']';
    }

    /** Ekspresi SQL: string JSON (hanya angka, kurung, koma) diurai menjadi tipe ClickHouse. */
    private static function json(string $json, string $tipe): string
    {
        return "JSONExtract('".$json."', '".$tipe."')";
    }
}
