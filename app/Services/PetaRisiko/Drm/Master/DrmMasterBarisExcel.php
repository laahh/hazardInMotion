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
 * Berkas Excel halaman Master per Baris: unduh (baris tersaring) → diedit user → unggah kembali.
 *  - Sheet "Master baris": satu baris per baris HIRA; yang bisa diedit aktivitas, sub aktivitas, bahaya.
 *  - Sheet "Label": SATU LABEL PER BARIS (ID baris · Jenis · Label) dengan dropdown, sehingga menambah label = menambah
 *    baris (dropdown Excel hanya bisa memilih satu nilai per sel, tidak bisa menambah ke sel berisi banyak label).
 *    ID yang muncul di sheet ini → label baris itu diganti persis isi sheet; ID yang tidak muncul → label tidak diubah.
 * Kolom dikenali dari JUDULNYA (bukan posisi). Kolom "(info)" hanya informasi dan diabaikan saat unggah.
 * Versi = sidik isian yang bisa diedit saat diunduh; dipakai mendeteksi baris yang diubah orang lain sesudahnya.
 */
final class DrmMasterBarisExcel
{
    public const SHEET = 'Master baris';

    public const SHEET_ITEM = 'Label';

    /** Sumber dropdown label (rentang bernama L_OBS / L_OAK / L_KET). */
    public const SHEET_LABEL = 'Daftar label';

    public const MAKS_BARIS = 5000;

    public const MAKS_LABEL = 30000;

    /** Baris kosong tambahan bervalidasi di sheet Label untuk menambah label. */
    private const BARIS_TAMBAHAN = 1000;

    /** kode jenis => nama di kolom Jenis sheet Label. */
    public const JENIS = ['OBS' => 'Observasi', 'OAK' => 'OAK', 'KET' => 'Ketidaksesuaian'];

    /** kunci => [judul kolom, lebar, bisa diedit]. */
    public const KOLOM = [
        'ri' => ['ID baris (jangan diubah)', 10, false],
        'aktivitas' => ['Aktivitas', 30, true],
        'sub' => ['Sub aktivitas', 30, true],
        'bahaya' => ['Bahaya - risiko', 40, true],
        'kb' => ['Risiko awal (info)', 13, false],
        'dept_hira' => ['Departemen HIRA (info)', 28, false],
        'idh' => ['ID HIRA (info)', 14, false],
        'kelompok' => ['Kelompok aktivitas (info)', 24, false],
        'grup' => ['Grup lokasi (info)', 28, false],
        'OBS' => ['Item Sumber Observasi (info, edit di sheet Label)', 40, false],
        'OAK' => ['Item Sumber OAK (info, edit di sheet Label)', 45, false],
        'KET' => ['Item Sumber Ketidaksesuaian (info, edit di sheet Label)', 45, false],
        'versi' => ['Versi (jangan diubah)', 14, false],
    ];

    /** Kolom sheet Label: kunci => [judul, lebar, bisa diedit]. */
    public const KOLOM_ITEM = [
        'ri' => ['ID baris', 10, true],
        'info' => ['Aktivitas · Bahaya (info)', 50, false],
        'jenis' => ['Jenis', 16, true],
        'label' => ['Label', 70, true],
    ];

    public const PETUNJUK = [
        'Sheet "Master baris": edit Aktivitas, Sub aktivitas, Bahaya - risiko. Kolom ID baris dan Versi jangan diubah; kolom (info) diabaikan saat unggah.',
        'Sheet "Label": SATU LABEL PER BARIS. Kolom Jenis = Observasi / OAK / Ketidaksesuaian (dropdown), kolom Label = dropdown sesuai Jenis.',
        'Menambah label: tambah baris di sheet Label (isi ID baris, pilih Jenis, pilih Label). Menghapus label: hapus barisnya. Label baru di luar daftar boleh diketik.',
        'Untuk setiap ID baris yang muncul di sheet Label, label baris itu diganti PERSIS isi sheet Label. ID yang tidak muncul sama sekali: labelnya tidak diubah.',
        'Menghapus SEMUA label satu baris: sisakan satu baris dengan ID itu, kosongkan Jenis dan Label.',
        'Label Ketidaksesuaian berformat "ketidaksesuaian / sub ketidaksesuaian" (dipisah spasi-garis miring-spasi).',
        'Site MARINE dan EKSPLORASI ditentukan departemen HIRA (Shipping / Port and Barging Operation -> MARINE, Geology and Exploration -> EKSPLORASI); site lain lewat grup lokasi.',
        'Mengubah teks Bahaya bisa membuat nilai risiko HIRA (kekerapan × konsekuensi) tidak tercocok lagi; pratinjau unggah memberi peringatan.',
        'Baris yang tidak ada di file tidak diubah. Baris yang diubah orang lain setelah file diunduh dilewati (konflik): unduh ulang lalu edit lagi.',
        'Perubahan dicatat di Riwayat (siapa, kapan, sebelum → sesudah) dan berlaku pada php artisan peta-risiko:sync berikutnya.',
    ];

    /**
     * @param  list<array<string, mixed>>  $baris  sudah tersaring; kunci sesuai KOLOM (nilai banyak = list<string>)
     * @param  list<string>  $grupAda  nama grup lokasi aktif (sheet bantuan)
     * @param  array<string, list<string>>  $daftarLabel  OBS / OAK / KET → label yang sudah ada (dropdown)
     * @return string path berkas sementara .xlsx
     */
    public function tulis(array $baris, array $grupAda, array $daftarLabel = []): string
    {
        $x = new Spreadsheet();
        $s = $x->getActiveSheet()->setTitle(self::SHEET);
        $this->kepala($s, self::KOLOM);
        $kunci = array_keys(self::KOLOM);
        foreach ($baris as $r => $b) {
            foreach ($kunci as $i => $k) {
                $v = $b[$k] ?? '';
                $s->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).($r + 2), is_array($v) ? implode("\n", $v) : (string) $v, DataType::TYPE_STRING);
            }
        }
        $this->badan($s, count($kunci), count($baris) + 1);

        $this->sheetLabel($x, $baris);
        $this->sheetPetunjuk($x, $grupAda);
        $this->sheetDaftar($x, $daftarLabel);
        $x->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'mbx').'.xlsx';
        (new Xlsx($x))->save($path);

        return $path;
    }

    /**
     * Baca berkas unggahan.
     *
     * @return array{baris: list<array<string, mixed>>, label: array<int, array<string, mixed>>, galatLabel: list<array{baris: int, ri: int, pesan: string}>}
     *   baris = isian sheet Master baris (kunci kolom yang bisa diedit + ri + versi + nomor baris Excel);
     *   label = ri → {OBS: list, OAK: list, KET: list, baris: nomor baris Excel pertama} untuk ID yang muncul di sheet Label
     */
    public function baca(string $path): array
    {
        $x = IOFactory::createReader('Xlsx')->setReadDataOnly(true)->load($path);
        $s = $x->getSheetByName(self::SHEET) ?? throw new RuntimeException('Sheet "'.self::SHEET.'" tidak ditemukan. Gunakan berkas hasil Unduh Excel.');
        [$data, $posisi] = $this->tabel($s, self::KOLOM, ['ri', 'versi']);
        if (! isset($posisi['ri'])) {
            throw new RuntimeException('Kolom "'.self::KOLOM['ri'][0].'" tidak ditemukan di baris judul sheet "'.self::SHEET.'".');
        }
        $baris = [];
        foreach ($data as $n => $row) {
            if (trim(implode('', array_map('strval', $row))) === '') {
                continue;
            }
            $isi = ['baris' => $n + 1];
            foreach ($posisi as $k => $i) {
                $isi[$k] = trim((string) ($row[$i] ?? ''));
            }
            $baris[] = $isi;
            if (count($baris) > self::MAKS_BARIS) {
                throw new RuntimeException('Maksimal '.self::MAKS_BARIS.' baris per unggahan.');
            }
        }
        $sl = $x->getSheetByName(self::SHEET_ITEM);

        return ['baris' => $baris, ...($sl === null ? ['label' => [], 'galatLabel' => []] : $this->bacaLabel($sl))];
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

    /** Nama jenis di sheet Label → kode (OBS / OAK / KET), null bila tidak dikenal. */
    public static function kodeJenis(string $nama): ?string
    {
        return match (mb_strtolower(trim($nama))) {
            'observasi', 'obs', 'item sumber observasi' => 'OBS',
            'oak', 'item sumber oak' => 'OAK',
            'ketidaksesuaian', 'ket', 'sub ketidaksesuaian', 'item sumber ketidaksesuaian' => 'KET',
            default => null,
        };
    }

    // ------------------------------------------------------------ sheet

    /** Satu label per baris; baris HIRA tanpa label tetap diberi satu baris (Jenis & Label kosong) agar mudah diisi. */
    private function sheetLabel(Spreadsheet $x, array $baris): void
    {
        $s = $x->createSheet()->setTitle(self::SHEET_ITEM);
        $this->kepala($s, self::KOLOM_ITEM);
        $n = 2;
        foreach ($baris as $b) {
            $info = trim((string) ($b['aktivitas'] ?? '').' · '.(string) ($b['bahaya'] ?? ''), ' ·');
            $isi = [];
            foreach (self::JENIS as $kode => $nama) {
                foreach ((array) ($b[$kode] ?? []) as $l) {
                    $isi[] = [$nama, (string) $l];
                }
            }
            foreach ($isi ?: [['', '']] as [$jenis, $label]) {
                $s->setCellValueExplicit('A'.$n, (string) $b['ri'], DataType::TYPE_STRING);
                $s->setCellValueExplicit('B'.$n, $info, DataType::TYPE_STRING);
                $s->setCellValueExplicit('C'.$n, $jenis, DataType::TYPE_STRING);
                $s->setCellValueExplicit('D'.$n, $label, DataType::TYPE_STRING);
                $n++;
            }
        }
        $akhir = $n - 1;
        $this->badan($s, count(self::KOLOM_ITEM), max(1, $akhir));
        $batas = max(2, $akhir) + self::BARIS_TAMBAHAN;

        $s->setDataValidation('C2:C'.$batas, (new DataValidation())->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)->setShowDropDown(true)
            ->setShowErrorMessage(true)->setErrorTitle('Jenis tidak dikenal')->setError('Pilih Observasi, OAK, atau Ketidaksesuaian.')
            ->setFormula1('"'.implode(',', self::JENIS).'"'));
        // Daftar label mengikuti Jenis di baris yang sama; bergaya INFORMASI: label baru di luar daftar tetap boleh.
        $s->setDataValidation('D2:D'.$batas, (new DataValidation())->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_INFORMATION)->setAllowBlank(true)->setShowDropDown(true)
            ->setShowErrorMessage(false)->setShowInputMessage(true)->setPromptTitle('Label')
            ->setPrompt('Pilih Jenis dulu, lalu pilih label dari daftar (atau ketik label baru). Satu label per baris.')
            ->setFormula1('INDIRECT(IF($C2="OAK","L_OAK",IF($C2="Ketidaksesuaian","L_KET","L_OBS")))'));
    }

    private function sheetPetunjuk(Spreadsheet $x, array $grupAda): void
    {
        $p = $x->createSheet()->setTitle('Petunjuk');
        $p->setCellValue('A1', 'Petunjuk Master per Baris')->getStyle('A1')->getFont()->setBold(true);
        foreach (self::PETUNJUK as $i => $t) {
            $p->setCellValue('A'.($i + 3), ($i + 1).'. '.$t);
        }
        $p->getColumnDimension('A')->setWidth(140);

        $g = $x->createSheet()->setTitle('Grup lokasi');
        $g->setCellValue('A1', 'Nama grup lokasi (info)')->getStyle('A1')->getFont()->setBold(true);
        foreach (array_values($grupAda) as $i => $n) {
            $g->setCellValueExplicit('A'.($i + 2), $n, DataType::TYPE_STRING);
        }
        $g->getColumnDimension('A')->setWidth(45);
    }

    /** Daftar label per jenis + rentang bernama L_OBS / L_OAK / L_KET untuk dropdown sheet Label. */
    private function sheetDaftar(Spreadsheet $x, array $daftarLabel): void
    {
        $d = $x->createSheet()->setTitle(self::SHEET_LABEL);
        $i = 0;
        foreach (self::JENIS as $kode => $nama) {
            $kol = Coordinate::stringFromColumnIndex(++$i);
            $daftar = array_values($daftarLabel[$kode] ?? []);
            $d->setCellValue($kol.'1', $nama)->getStyle($kol.'1')->getFont()->setBold(true);
            $d->getColumnDimension($kol)->setWidth(55);
            foreach ($daftar as $n => $l) {
                $d->setCellValueExplicit($kol.($n + 2), $l, DataType::TYPE_STRING);
            }
            $x->addNamedRange(new NamedRange('L_'.$kode, $d, '$'.$kol.'$2:$'.$kol.'$'.max(2, count($daftar) + 1)));
        }
    }

    /**
     * @return array{label: array<int, array<string, mixed>>, galatLabel: list<array{baris: int, ri: int, pesan: string}>}
     */
    private function bacaLabel(Worksheet $s): array
    {
        [$data, $posisi] = $this->tabel($s, self::KOLOM_ITEM, []);
        foreach (['ri', 'jenis', 'label'] as $k) {
            if (! isset($posisi[$k])) {
                throw new RuntimeException('Kolom "'.self::KOLOM_ITEM[$k][0].'" tidak ditemukan di sheet "'.self::SHEET_ITEM.'".');
            }
        }
        $label = $galat = [];
        $jumlah = 0;
        foreach ($data as $n => $row) {
            $ri = trim((string) ($row[$posisi['ri']] ?? ''));
            $jenis = trim((string) ($row[$posisi['jenis']] ?? ''));
            $teks = trim(preg_replace('/\s+/u', ' ', (string) ($row[$posisi['label']] ?? '')));
            if ($ri === '' && $jenis === '' && $teks === '') {
                continue;
            }
            if (++$jumlah > self::MAKS_LABEL) {
                throw new RuntimeException('Maksimal '.self::MAKS_LABEL.' baris di sheet "'.self::SHEET_ITEM.'".');
            }
            if (! ctype_digit($ri)) {
                $galat[] = ['baris' => $n + 1, 'ri' => 0, 'pesan' => 'Sheet Label baris '.($n + 1).': ID baris kosong / bukan angka.'];

                continue;
            }
            $id = (int) $ri;
            $label[$id] ??= ['OBS' => [], 'OAK' => [], 'KET' => [], 'baris' => $n + 1];
            if ($jenis === '' && $teks === '') {
                continue; // penanda: ID ini ikut diatur (semua labelnya bisa jadi kosong)
            }
            $kode = self::kodeJenis($jenis);
            if ($kode === null || $teks === '') {
                $galat[] = ['baris' => $n + 1, 'ri' => $id, 'pesan' => 'Sheet Label baris '.($n + 1).': '
                    .($kode === null ? 'Jenis "'.$jenis.'" tidak dikenal (pilih Observasi, OAK, atau Ketidaksesuaian).' : 'Label kosong.')];

                continue;
            }
            $label[$id][$kode][] = $teks;
        }

        return ['label' => $label, 'galatLabel' => $galat];
    }

    /** @param  array<string, array{0: string, 1: int, 2: bool}>  $kolom */
    private function kepala(Worksheet $s, array $kolom): void
    {
        $i = 0;
        foreach ($kolom as [$judul, $lebar, $edit]) {
            $kol = Coordinate::stringFromColumnIndex(++$i);
            $s->setCellValue($kol.'1', $judul);
            $s->getColumnDimension($kol)->setWidth($lebar);
            $s->getStyle($kol.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($edit ? 'DCE6FF' : 'E5E7EB');
        }
        $s->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($kolom)).'1')->getFont()->setBold(true);
        $s->freezePane('B2');
    }

    private function badan(Worksheet $s, int $nKolom, int $akhir): void
    {
        $kol = Coordinate::stringFromColumnIndex($nKolom);
        $s->getStyle('A2:'.$kol.max(2, $akhir))->getAlignment()->setWrapText(true)->setVertical('top');
        $s->setAutoFilter('A1:'.$kol.max(1, $akhir));
    }

    /**
     * Data sheet tanpa baris judul + posisi kolom (dikenali dari judul, huruf besar/kecil diabaikan).
     *
     * @param  array<string, array{0: string, 1: int, 2: bool}>  $kolom
     * @param  list<string>  $selalu  kunci yang dibaca walau tidak bisa diedit
     * @return array{0: array<int, list<mixed>>, 1: array<string, int>}
     */
    private function tabel(Worksheet $s, array $kolom, array $selalu): array
    {
        $data = $s->toArray(null, false, false, false);
        $judul = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $data[0] ?? []);
        $posisi = [];
        foreach ($kolom as $k => [$teks, , $edit]) {
            $i = array_search(mb_strtolower($teks), $judul, true);
            if ($i !== false && ($edit || in_array($k, $selalu, true))) {
                $posisi[$k] = $i;
            }
        }

        return [array_slice($data, 1, null, true), $posisi];
    }
}
