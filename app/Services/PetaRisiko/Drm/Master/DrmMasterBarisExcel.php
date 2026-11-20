<?php

namespace App\Services\PetaRisiko\Drm\Master;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Berkas Excel halaman Master per Baris: unduh (baris tersaring) → diedit user → unggah kembali.
 * Kolom dikenali dari JUDULNYA (bukan posisi), jadi urutan kolom boleh digeser. Kolom "(info)" hanya informasi dan
 * diabaikan saat unggah. Isian banyak nilai (grup lokasi, label SAP) = satu nilai per baris di dalam sel.
 * Versi = sidik isian yang bisa diedit saat diunduh; dipakai mendeteksi baris yang diubah orang lain sesudahnya.
 */
final class DrmMasterBarisExcel
{
    public const SHEET = 'Master baris';

    public const MAKS_BARIS = 5000;

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
        'OBS' => ['Item Sumber Observasi', 40, true],
        'OAK' => ['Item Sumber OAK', 45, true],
        'KET' => ['Item Sumber Ketidaksesuaian', 45, true],
        'versi' => ['Versi (jangan diubah)', 14, false],
    ];

    public const PETUNJUK = [
        'Edit langsung di sheet "Master baris", lalu unggah kembali di halaman Master per Baris → Unggah Excel.',
        'Kolom ID baris dan Versi jangan diubah. Kolom bertanda (info) hanya informasi dan diabaikan saat unggah.',
        'Yang bisa diedit: Aktivitas, Sub aktivitas, Bahaya - risiko, Item Sumber Observasi, Item Sumber OAK, Item Sumber Ketidaksesuaian.',
        'Item Sumber: satu label per baris di dalam sel (Alt+Enter untuk baris baru). Sel dikosongkan = semua label di kolom itu dihapus.',
        'Item Sumber Ketidaksesuaian ditulis "ketidaksesuaian / sub ketidaksesuaian" (dipisah spasi-garis miring-spasi).',
        'Site MARINE dan EKSPLORASI ditentukan departemen HIRA (Shipping / Port and Barging Operation -> MARINE, Geology and Exploration -> EKSPLORASI); site lain lewat grup lokasi.',
        'Mengubah teks Bahaya bisa membuat nilai risiko HIRA (kekerapan × konsekuensi) tidak tercocok lagi; pratinjau unggah memberi peringatan.',
        'Baris yang tidak ada di file tidak diubah. Baris yang diubah orang lain setelah file diunduh dilewati (konflik): unduh ulang lalu edit lagi.',
        'Perubahan dicatat di Riwayat (siapa, kapan, sebelum → sesudah) dan berlaku pada php artisan peta-risiko:sync berikutnya.',
    ];

    /**
     * @param  list<array<string, mixed>>  $baris  sudah tersaring; kunci sesuai KOLOM (nilai banyak = list<string>)
     * @param  list<string>  $grupAda  nama grup lokasi aktif (sheet bantuan)
     * @return string path berkas sementara .xlsx
     */
    public function tulis(array $baris, array $grupAda): string
    {
        $x = new Spreadsheet();
        $s = $x->getActiveSheet()->setTitle(self::SHEET);
        $kunci = array_keys(self::KOLOM);
        foreach ($kunci as $i => $k) {
            [$judul, $lebar, $edit] = self::KOLOM[$k];
            $kol = Coordinate::stringFromColumnIndex($i + 1);
            $s->setCellValue($kol.'1', $judul);
            $s->getColumnDimension($kol)->setWidth($lebar);
            $s->getStyle($kol.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($edit ? 'DCE6FF' : 'E5E7EB');
        }
        $s->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($kunci)).'1')->getFont()->setBold(true);
        $s->freezePane('B2');

        foreach ($baris as $r => $b) {
            foreach ($kunci as $i => $k) {
                $v = $b[$k] ?? '';
                $s->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).($r + 2), is_array($v) ? implode("\n", $v) : (string) $v,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }
        $akhir = count($baris) + 1;
        $s->getStyle('A2:'.Coordinate::stringFromColumnIndex(count($kunci)).max(2, $akhir))->getAlignment()->setWrapText(true)->setVertical('top');
        $s->setAutoFilter('A1:'.Coordinate::stringFromColumnIndex(count($kunci)).max(1, $akhir));

        $p = $x->createSheet()->setTitle('Petunjuk');
        $p->setCellValue('A1', 'Petunjuk Master per Baris')->getStyle('A1')->getFont()->setBold(true);
        foreach (self::PETUNJUK as $i => $t) {
            $p->setCellValue('A'.($i + 3), ($i + 1).'. '.$t);
        }
        $p->getColumnDimension('A')->setWidth(130);

        $g = $x->createSheet()->setTitle('Grup lokasi');
        $g->setCellValue('A1', 'Nama grup lokasi (pakai persis)')->getStyle('A1')->getFont()->setBold(true);
        foreach (array_values($grupAda) as $i => $n) {
            $g->setCellValueExplicit('A'.($i + 2), $n, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $g->getColumnDimension('A')->setWidth(45);
        $x->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'mbx').'.xlsx';
        (new Xlsx($x))->save($path);

        return $path;
    }

    /**
     * Baca sheet "Master baris": baris data → isian per kunci kolom yang bisa diedit + ri + versi.
     * Kolom yang tidak ada di file → kunci itu tidak dikirim (tidak diubah).
     *
     * @return list<array<string, mixed>>  [{baris: nomor baris Excel, ri, versi, …isian (string mentah)}]
     */
    public function baca(string $path): array
    {
        $x = IOFactory::createReader('Xlsx')->setReadDataOnly(true)->load($path);
        $s = $x->getSheetByName(self::SHEET) ?? throw new RuntimeException('Sheet "'.self::SHEET.'" tidak ditemukan. Gunakan berkas hasil Unduh Excel.');
        $data = $s->toArray(null, false, false, false);
        $judul = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $data[0] ?? []);
        $posisi = [];
        foreach (self::KOLOM as $k => [$teks, , $edit]) {
            $i = array_search(mb_strtolower($teks), $judul, true);
            if ($i !== false && ($edit || in_array($k, ['ri', 'versi'], true))) {
                $posisi[$k] = $i;
            }
        }
        if (! isset($posisi['ri'])) {
            throw new RuntimeException('Kolom "'.self::KOLOM['ri'][0].'" tidak ditemukan di baris judul.');
        }
        $hasil = [];
        foreach (array_slice($data, 1, null, true) as $n => $row) {
            if (trim((string) ($row[$posisi['ri']] ?? '')) === '' && trim(implode('', array_map('strval', $row))) === '') {
                continue;
            }
            $isi = ['baris' => $n + 1];
            foreach ($posisi as $k => $i) {
                $isi[$k] = trim((string) ($row[$i] ?? ''));
            }
            $hasil[] = $isi;
            if (count($hasil) > self::MAKS_BARIS) {
                throw new RuntimeException('Maksimal '.self::MAKS_BARIS.' baris per unggahan.');
            }
        }

        return $hasil;
    }

    /** Sidik isian yang bisa diedit (urutan nilai banyak diabaikan). */
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
}
