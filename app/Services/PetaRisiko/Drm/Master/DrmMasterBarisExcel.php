<?php

namespace App\Services\PetaRisiko\Drm\Master;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Berkas Excel halaman Master per Baris, SATU sheet (keputusan user 20 Nov 2026): satu baris = satu label SAP
 * (ID baris · aktivitas · sub · bahaya · Jenis · Label · info · Versi). Menambah label = menambah baris; dropdown
 * Jenis dan Label (mengikuti Jenis) per sel, jadi tidak ada label yang tertimpa. Baris HIRA tanpa label = satu baris
 * dengan Jenis & Label kosong. Aktivitas / sub / bahaya diulang per baris label; mengubah di salah satu baris cukup.
 * ID yang muncul di berkas → label baris itu diganti persis isi berkas; ID yang tidak muncul → tidak diubah.
 * Sumber dropdown di sheet tersembunyi "Daftar label" (rentang bernama L_OBS / L_OAK / L_KET).
 * Kolom dikenali dari JUDULNYA (bukan posisi). Versi = sidik isian yang bisa diedit saat diunduh (deteksi konflik).
 */
final class DrmMasterBarisExcel
{
    public const SHEET = 'Master baris';

    public const SHEET_LABEL = 'Daftar label';

    public const MAKS_BARIS = 30000;

    /** Baris kosong bervalidasi di bawah data untuk menambah label. */
    private const BARIS_TAMBAHAN = 1000;

    /** kode jenis => nama di kolom Jenis. */
    public const JENIS = ['OBS' => 'Observasi', 'OAK' => 'OAK', 'KET' => 'Ketidaksesuaian'];

    /** kunci => [judul kolom, lebar, bisa diedit]. */
    public const KOLOM = [
        'ri' => ['ID baris (jangan diubah)', 10, false],
        'aktivitas' => ['Aktivitas', 28, true],
        'sub' => ['Sub aktivitas', 28, true],
        'bahaya' => ['Bahaya - risiko', 36, true],
        'jenis' => ['Jenis', 16, true],
        'label' => ['Label', 60, true],
        'kb' => ['Risiko awal (info)', 13, false],
        'site' => ['Site (info)', 14, false],
        'dept_hira' => ['Departemen HIRA (info)', 26, false],
        'idh' => ['ID HIRA (info)', 13, false],
        'kelompok' => ['Kelompok aktivitas (info)', 22, false],
        'grup' => ['Grup lokasi (info)', 24, false],
        'versi' => ['Versi (jangan diubah)', 14, false],
    ];

    /** Keterangan singkat di sel input (petunjuk tanpa sheet tambahan). */
    private const PESAN = [
        'jenis' => 'Pilih Observasi, OAK, atau Ketidaksesuaian. Satu baris = satu label.',
        'label' => 'Pilih label sesuai Jenis (atau ketik label baru). Tambah label = tambah baris dengan ID baris yang sama; hapus label = hapus barisnya. Ketidaksesuaian: "ketidaksesuaian / sub".',
        'aktivitas' => 'Diulang di setiap baris label ID yang sama; cukup ubah di salah satu baris.',
    ];

    /**
     * @param  list<array<string, mixed>>  $baris  sudah tersaring; kunci sesuai KOLOM + OBS/OAK/KET (list label)
     * @param  array<string, list<string>>  $daftarLabel  OBS / OAK / KET → label yang sudah ada (dropdown)
     * @return string path berkas sementara .xlsx
     */
    public function tulis(array $baris, array $daftarLabel = []): string
    {
        $x = new Spreadsheet();
        $s = $x->getActiveSheet()->setTitle(self::SHEET);
        $kunci = array_keys(self::KOLOM);
        $i = 0;
        foreach (self::KOLOM as [$judul, $lebar, $edit]) {
            $kol = Coordinate::stringFromColumnIndex(++$i);
            $s->setCellValue($kol.'1', $judul);
            $s->getColumnDimension($kol)->setWidth($lebar);
            $s->getStyle($kol.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($edit ? 'DCE6FF' : 'E5E7EB');
        }
        $s->getStyle('A1:'.$this->kol('versi').'1')->getFont()->setBold(true);
        $s->freezePane('E2');

        $n = 2;
        foreach ($baris as $b) {
            $label = [];
            foreach (self::JENIS as $kode => $nama) {
                foreach ((array) ($b[$kode] ?? []) as $l) {
                    $label[] = [$nama, (string) $l];
                }
            }
            foreach ($label ?: [['', '']] as [$jenis, $teks]) {
                foreach ($kunci as $c => $k) {
                    $v = match ($k) { 'jenis' => $jenis, 'label' => $teks, default => $b[$k] ?? '' };
                    $s->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).$n, is_array($v) ? implode("\n", $v) : (string) $v, DataType::TYPE_STRING);
                }
                $n++;
            }
        }
        $akhir = max(2, $n - 1);
        $s->getStyle('A2:'.$this->kol('versi').$akhir)->getAlignment()->setWrapText(true)->setVertical('top');
        $s->setAutoFilter('A1:'.$this->kol('versi').max(1, $n - 1));

        $this->sheetDaftar($x, $daftarLabel);
        $this->dropdown($s, $akhir + self::BARIS_TAMBAHAN);
        $x->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'mbx').'.xlsx';
        (new Xlsx($x))->save($path);

        return $path;
    }

    /**
     * Baca berkas unggahan → per ID baris.
     *
     * @return array{baris: list<array<string, mixed>>, label: array<int, array<string, mixed>>, galatLabel: list<array{baris: int, ri: int, pesan: string}>}
     *   baris = per ri: {baris: nomor baris Excel pertama, ri, versi, aktivitas|sub|bahaya: list nilai berbeda di berkas}
     *   label = ri → {OBS, OAK, KET: list label, baris}
     */
    public function baca(string $path): array
    {
        $x = IOFactory::createReader('Xlsx')->setReadDataOnly(true)->load($path);
        $s = $x->getSheetByName(self::SHEET) ?? throw new RuntimeException('Sheet "'.self::SHEET.'" tidak ditemukan. Gunakan berkas hasil Unduh Excel.');
        $data = $s->toArray(null, false, false, false);
        $judul = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $data[0] ?? []);
        $p = [];
        foreach (self::KOLOM as $k => [$teks, , $edit]) {
            $i = array_search(mb_strtolower($teks), $judul, true);
            if ($i !== false && ($edit || in_array($k, ['ri', 'versi'], true))) {
                $p[$k] = $i;
            }
        }
        foreach (['ri', 'jenis', 'label'] as $k) {
            if (! isset($p[$k])) {
                throw new RuntimeException('Kolom "'.self::KOLOM[$k][0].'" tidak ditemukan di baris judul. Gunakan berkas hasil Unduh Excel.');
            }
        }
        $sel = fn (array $row, string $k) => isset($p[$k]) ? trim(preg_replace('/\s+/u', ' ', (string) ($row[$p[$k]] ?? ''))) : null;

        $per = $label = $galat = [];
        $jumlah = 0;
        foreach (array_slice($data, 1, null, true) as $n => $row) {
            $ri = (string) $sel($row, 'ri');
            if (trim(implode('', array_map('strval', $row))) === '') {
                continue;
            }
            if (++$jumlah > self::MAKS_BARIS) {
                throw new RuntimeException('Maksimal '.self::MAKS_BARIS.' baris per unggahan.');
            }
            $nomor = $n + 1;
            if (! ctype_digit($ri)) {
                $galat[] = ['baris' => $nomor, 'ri' => 0, 'pesan' => 'Baris Excel '.$nomor.': ID baris kosong / bukan angka.'];

                continue;
            }
            $id = (int) $ri;
            $per[$id] ??= ['baris' => $nomor, 'ri' => $ri, 'versi' => ''];
            if (($v = (string) $sel($row, 'versi')) !== '' && $per[$id]['versi'] === '') {
                $per[$id]['versi'] = $v;
            }
            foreach (['aktivitas', 'sub', 'bahaya'] as $k) {
                if (($v = $sel($row, $k)) !== null) {
                    $per[$id][$k][$v] = true;
                }
            }
            $label[$id] ??= ['OBS' => [], 'OAK' => [], 'KET' => [], 'baris' => $nomor];
            $jenis = (string) $sel($row, 'jenis');
            $teks = (string) $sel($row, 'label');
            if ($jenis === '' && $teks === '') {
                continue; // baris tanpa label (penanda ID ikut diatur)
            }
            $kode = self::kodeJenis($jenis);
            if ($kode === null || $teks === '') {
                $galat[] = ['baris' => $nomor, 'ri' => $id, 'pesan' => 'Baris Excel '.$nomor.': '
                    .($kode === null ? 'Jenis "'.$jenis.'" tidak dikenal (pilih Observasi, OAK, atau Ketidaksesuaian).' : 'Label kosong.')];

                continue;
            }
            $label[$id][$kode][] = $teks;
        }
        $baris = array_map(function (array $r) {
            foreach (['aktivitas', 'sub', 'bahaya'] as $k) {
                if (isset($r[$k])) {
                    $r[$k] = array_keys($r[$k]);
                }
            }

            return $r;
        }, array_values($per));

        return ['baris' => $baris, 'label' => $label, 'galatLabel' => $galat];
    }

    /** Sidik isian yang bisa diedit (urutan label diabaikan). */
    public static function versi(array $isian): string
    {
        $kanon = [];
        foreach (['aktivitas', 'sub', 'bahaya', 'OBS', 'OAK', 'KET'] as $k) {
            $v = $isian[$k] ?? '';
            if (is_array($v)) {
                $v = array_map(fn ($x) => mb_strtolower(trim((string) $x)), $v);
                sort($v);
                $v = implode("\n", $v);
            }
            $kanon[] = mb_strtolower(trim((string) $v));
        }

        return substr(md5(implode("\x1f", $kanon)), 0, 12);
    }

    /** Nama jenis → kode (OBS / OAK / KET), null bila tidak dikenal. */
    public static function kodeJenis(string $nama): ?string
    {
        return match (mb_strtolower(trim($nama))) {
            'observasi', 'obs', 'item sumber observasi' => 'OBS',
            'oak', 'item sumber oak' => 'OAK',
            'ketidaksesuaian', 'ket', 'sub ketidaksesuaian', 'item sumber ketidaksesuaian' => 'KET',
            default => null,
        };
    }

    /** Huruf kolom untuk kunci KOLOM. */
    public function kol(string $kunci): string
    {
        return Coordinate::stringFromColumnIndex(array_search($kunci, array_keys(self::KOLOM), true) + 1);
    }

    /** Dropdown Jenis (ketat) dan Label (mengikuti Jenis di baris yang sama; label baru tetap boleh) + petunjuk sel. */
    private function dropdown(Worksheet $s, int $batas): void
    {
        $j = $this->kol('jenis');
        $s->setDataValidation($j.'2:'.$j.$batas, (new DataValidation())->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)->setShowDropDown(true)
            ->setShowErrorMessage(true)->setErrorTitle('Jenis tidak dikenal')->setError('Pilih Observasi, OAK, atau Ketidaksesuaian.')
            ->setShowInputMessage(true)->setPromptTitle('Jenis')->setPrompt(self::PESAN['jenis'])
            ->setFormula1('"'.implode(',', self::JENIS).'"'));
        $l = $this->kol('label');
        $s->setDataValidation($l.'2:'.$l.$batas, (new DataValidation())->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_INFORMATION)->setAllowBlank(true)->setShowDropDown(true)
            ->setShowErrorMessage(false)->setShowInputMessage(true)->setPromptTitle('Label')->setPrompt(self::PESAN['label'])
            ->setFormula1('INDIRECT(IF($'.$j.'2="OAK","L_OAK",IF($'.$j.'2="Ketidaksesuaian","L_KET","L_OBS")))'));
        $a = $this->kol('aktivitas');
        $s->setDataValidation($a.'2:'.$this->kol('bahaya').$batas, (new DataValidation())->setType(DataValidation::TYPE_NONE)
            ->setShowInputMessage(true)->setPromptTitle('Aktivitas · Sub · Bahaya')->setPrompt(self::PESAN['aktivitas']));
    }

    /** Daftar label per jenis di sheet TERSEMBUNYI + rentang bernama L_OBS / L_OAK / L_KET untuk dropdown Label. */
    private function sheetDaftar(Spreadsheet $x, array $daftarLabel): void
    {
        $d = $x->createSheet()->setTitle(self::SHEET_LABEL);
        $i = 0;
        foreach (self::JENIS as $kode => $nama) {
            $kol = Coordinate::stringFromColumnIndex(++$i);
            $daftar = array_values($daftarLabel[$kode] ?? []);
            $d->setCellValue($kol.'1', $nama);
            foreach ($daftar as $n => $l) {
                $d->setCellValueExplicit($kol.($n + 2), $l, DataType::TYPE_STRING);
            }
            $x->addNamedRange(new NamedRange('L_'.$kode, $d, '$'.$kol.'$2:$'.$kol.'$'.max(2, count($daftar) + 1)));
        }
        $d->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }
}
