<?php

namespace App\Services\PetaRisiko;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Pembaca .xlsx berbasis stream (XMLReader) untuk file besar.
 * PhpSpreadsheet memuat semua sel ke memori (±1 KB/sel), terlalu berat untuk
 * workbook mapping ±1 juta sel. Kelas ini hanya membaca nilai (tanpa gaya/rumus).
 */
class XlsxStreamReader
{
    private ZipArchive $zip;

    /** @var array<int, string> */
    private array $sharedStrings = [];

    /** @var array<string, string> nama sheet => path xml di dalam zip */
    private array $sheets = [];

    public function __construct(string $path)
    {
        if (! is_file($path)) {
            throw new RuntimeException("File tidak ditemukan: {$path}");
        }

        $this->zip = new ZipArchive();
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Bukan file xlsx yang valid: {$path}");
        }

        $this->loadSheets();
        $this->loadSharedStrings();
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheets);
    }

    /**
     * Baris sheet sebagai array asosiatif (header baris pertama => nilai string).
     *
     * @return \Generator<int, array<string, string>>
     */
    public function rows(string $sheet): \Generator
    {
        if (! isset($this->sheets[$sheet])) {
            throw new RuntimeException("Sheet '{$sheet}' tidak ada. Tersedia: ".implode(', ', $this->sheetNames()));
        }

        $header = null;
        foreach ($this->rawRows($this->sheets[$sheet]) as $cells) {
            if ($header === null) {
                $header = array_map('trim', $cells);
                continue;
            }
            $row = [];
            foreach ($header as $i => $name) {
                $row[$name] = trim((string) ($cells[$i] ?? ''));
            }
            yield $row;
        }
    }

    /** @return \Generator<int, array<int, string>> */
    private function rawRows(string $xmlPath): \Generator
    {
        $xml = new XMLReader();
        $xml->XML($this->read($xmlPath), null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);

        $cells = null;
        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'row') {
                $cells = [];
                if ($xml->isEmptyElement) {
                    yield [];
                    $cells = null;
                }
                continue;
            }
            if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->localName === 'row') {
                if ($cells !== null) {
                    $max = $cells === [] ? -1 : max(array_keys($cells));
                    $out = [];
                    for ($i = 0; $i <= $max; $i++) {
                        $out[$i] = $cells[$i] ?? '';
                    }
                    yield $out;
                }
                $cells = null;
                continue;
            }
            if ($cells !== null && $xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'c') {
                [$col, $value] = $this->readCell($xml);
                $cells[$col] = $value;
            }
        }
        $xml->close();
    }

    /** @return array{0:int, 1:string} */
    private function readCell(XMLReader $xml): array
    {
        $col = self::columnIndex((string) $xml->getAttribute('r'));
        $type = (string) $xml->getAttribute('t');
        if ($xml->isEmptyElement) {
            return [$col, ''];
        }

        $value = '';
        $depth = $xml->depth;
        while ($xml->read() && ! ($xml->nodeType === XMLReader::END_ELEMENT && $xml->depth === $depth)) {
            if (in_array($xml->nodeType, [XMLReader::TEXT, XMLReader::CDATA, XMLReader::SIGNIFICANT_WHITESPACE], true)) {
                $value .= $xml->value;
            }
        }

        if ($type === 's') {
            $value = $this->sharedStrings[(int) $value] ?? '';
        } elseif ($type === 'b') {
            $value = $value === '1' ? 'TRUE' : 'FALSE';
        }

        return [$col, $value];
    }

    public static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref)) ?: 'A';
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private function loadSheets(): void
    {
        $rels = [];
        $relXml = simplexml_load_string($this->read('xl/_rels/workbook.xml.rels'));
        foreach ($relXml->Relationship as $rel) {
            $target = ltrim((string) $rel['Target'], '/');
            $rels[(string) $rel['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        $wb = simplexml_load_string($this->read('xl/workbook.xml'));
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($wb->xpath('//m:sheets/m:sheet') as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($rels[$rid])) {
                $this->sheets[(string) $sheet['name']] = $rels[$rid];
            }
        }
    }

    private function loadSharedStrings(): void
    {
        if ($this->zip->locateName('xl/sharedStrings.xml') === false) {
            return;
        }

        $xml = new XMLReader();
        $xml->XML($this->read('xl/sharedStrings.xml'), null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        $current = null;
        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'si') {
                $current = '';
            } elseif ($current !== null && $xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'rPh') {
                $xml->next(); // teks fonetik (furigana) bukan bagian nilai
            } elseif ($current !== null && ($xml->nodeType === XMLReader::TEXT || $xml->nodeType === XMLReader::SIGNIFICANT_WHITESPACE)) {
                $current .= $xml->value;
            } elseif ($xml->nodeType === XMLReader::END_ELEMENT && $xml->localName === 'si') {
                $this->sharedStrings[] = (string) $current;
                $current = null;
            }
        }
        $xml->close();
    }

    private function read(string $name): string
    {
        $data = $this->zip->getFromName($name);
        if ($data === false) {
            throw new RuntimeException("Bagian '{$name}' tidak ada di dalam xlsx.");
        }

        return $data;
    }
}
