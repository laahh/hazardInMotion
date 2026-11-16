<?php

namespace App\Services\PetaRisiko\Drm;

use RuntimeException;
use ZipArchive;

/**
 * Penulis XLSX minimal yang menulis baris langsung ke file (hemat memori untuk puluhan ribu baris;
 * PhpSpreadsheet memakan ratusan MB untuk ±500 ribu sel). Teks ditulis sebagai inline string.
 * Fitur: baris judul tebal + dibekukan + filter, lebar kolom, teks panjang dibungkus.
 */
class DrmXlsxWriter
{
    /** Gaya sel di styles.xml: 0 = biasa, 1 = judul, 2 = teks dibungkus, 3 = angka. */
    private const GAYA_JUDUL = 1;

    private const GAYA_BUNGKUS = 2;

    private const GAYA_ANGKA = 3;

    private const GAYA_POLOS = 4;

    /** @var list<array{nama: string, file: string}> */
    private array $sheet = [];

    private string $folder;

    public function __construct()
    {
        $this->folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'drm-xlsx-'.uniqid();
        if (! mkdir($this->folder, 0700, true) && ! is_dir($this->folder)) {
            throw new RuntimeException('Tidak bisa membuat folder sementara untuk Excel.');
        }
    }

    /**
     * @param  list<string>  $judul
     * @param  iterable<list<string|int|float|bool|null>>  $baris
     * @param  list<int>  $lebar  lebar kolom (karakter)
     * @param  list<int>  $bungkus  indeks kolom (0-based) yang teksnya dibungkus
     * @param  list<int>  $polos  indeks kolom angka tanpa pemisah ribuan (ID)
     */
    public function tambahSheet(string $nama, array $judul, iterable $baris, array $lebar = [], array $bungkus = [], array $polos = []): void
    {
        $file = $this->folder.DIRECTORY_SEPARATOR.'sheet'.(count($this->sheet) + 1).'.xml';
        $h = fopen($file, 'wb');
        $kolomTerakhir = $this->kolom(count($judul) - 1);
        fwrite($h, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        if ($lebar !== []) {
            fwrite($h, '<cols>');
            foreach ($lebar as $i => $w) {
                fwrite($h, '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>');
            }
            fwrite($h, '</cols>');
        }
        fwrite($h, '<sheetData>');
        fwrite($h, $this->baris(1, $judul, array_fill(0, count($judul), self::GAYA_JUDUL)));
        $bungkus = array_flip($bungkus);
        $polos = array_flip($polos);
        $n = 1;
        foreach ($baris as $isi) {
            $n++;
            $gaya = [];
            foreach (array_keys($isi) as $i) {
                $gaya[$i] = isset($bungkus[$i]) ? self::GAYA_BUNGKUS : (isset($polos[$i]) ? self::GAYA_POLOS : 0);
            }
            fwrite($h, $this->baris($n, $isi, $gaya));
        }
        fwrite($h, '</sheetData><autoFilter ref="A1:'.$kolomTerakhir.max(1, $n).'"/></worksheet>');
        fclose($h);
        $this->sheet[] = ['nama' => mb_substr($nama, 0, 31), 'file' => $file];
    }

    public function simpan(string $tujuan): void
    {
        $zip = new ZipArchive();
        if ($zip->open($tujuan, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Tidak bisa menulis {$tujuan}.");
        }
        $sheetXml = $relXml = $tipeXml = '';
        foreach ($this->sheet as $i => $s) {
            $no = $i + 1;
            $sheetXml .= '<sheet name="'.$this->esc($s['nama']).'" sheetId="'.$no.'" r:id="rId'.$no.'"/>';
            $relXml .= '<Relationship Id="rId'.$no.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$no.'.xml"/>';
            $tipeXml .= '<Override PartName="/xl/worksheets/sheet'.$no.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $zip->addFile($s['file'], 'xl/worksheets/sheet'.$no.'.xml');
        }
        $nGaya = count($this->sheet) + 1;
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$tipeXml.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetXml.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relXml
            .'<Relationship Id="rId'.$nGaya.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->gaya());
        $zip->close();
        $this->bersihkan();
    }

    public function __destruct()
    {
        $this->bersihkan();
    }

    /**
     * @param  list<string|int|float|bool|null>  $isi
     * @param  array<int, int>  $gaya
     */
    private function baris(int $no, array $isi, array $gaya): string
    {
        $xml = '<row r="'.$no.'">';
        foreach (array_values($isi) as $i => $v) {
            $ref = $this->kolom($i).$no;
            $g = $gaya[$i] ?? 0;
            if (is_int($v) || is_float($v)) {
                $xml .= '<c r="'.$ref.'" s="'.($g ?: self::GAYA_ANGKA).'"><v>'.$v.'</v></c>';
            } elseif (is_bool($v)) {
                $xml .= '<c r="'.$ref.'" t="inlineStr"'.($g ? ' s="'.$g.'"' : '').'><is><t>'.($v ? 'Ya' : 'Tidak').'</t></is></c>';
            } elseif ($v !== null && $v !== '') {
                $teks = mb_substr((string) $v, 0, 32000);
                $xml .= '<c r="'.$ref.'" t="inlineStr"'.($g ? ' s="'.$g.'"' : '').'><is><t xml:space="preserve">'.$this->esc($teks).'</t></is></c>';
            }
        }

        return $xml.'</row>';
    }

    private function kolom(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private function esc(string $s): string
    {
        // Buang karakter kontrol yang tidak sah di XML, lalu escape.
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function gaya(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF16294F"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="top"/></xf>'
            .'</cellXfs></styleSheet>';
    }

    private function bersihkan(): void
    {
        if (! is_dir($this->folder)) {
            return;
        }
        foreach (glob($this->folder.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->folder);
    }
}
