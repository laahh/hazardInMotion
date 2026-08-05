<?php

declare(strict_types=1);

namespace App\Support\MonitoringSafetyEngineering;

/**
 * Struktur kolom Excel Monitoring Safety Engineering — selaras spreadsheet manajemen.
 */
final class MonitoringSafetyEngineeringExcelStructure
{
    public const EXCEL_GROUP_HEADER_ROW = 1;

    public const EXCEL_COLUMN_HEADER_ROW = 2;

    public const EXCEL_DATA_START_ROW = 3;

    public const COL_NO = 0;

    public const COL_SITE = 1;

    public const COL_PERUSAHAAN = 2;

    public const COL_SUMBER_REKAYASA = 3;

    public const COL_PELAKSANA_REKAYASA = 4;

    public const COL_PENGENDALIAN_REKAYASA = 5;

    public const COL_TANGGAL_IDEATION = 6;

    public const COL_KT_DUE = 7;

    public const COL_KT_STATUS = 8;

    public const COL_KT_EVIDENCE = 9;

    public const COL_PENGADAAN_DUE = 10;

    public const COL_PENGADAAN_STATUS = 11;

    public const COL_PENGADAAN_EVIDENCE = 12;

    public const COL_UJI_COBA_DUE = 13;

    public const COL_UJI_COBA_STATUS = 14;

    public const COL_UJI_COBA_EVIDENCE = 15;

    public const COL_STD_DUE = 16;

    public const COL_STD_STATUS = 17;

    public const COL_STD_EVIDENCE = 18;

    public const COL_REP_DUE = 19;

    public const COL_REP_TOTAL_POPULASI = 20;

    public const COL_REP_SATUAN = 21;

    public const COL_REP_TARGET = 22;

    public const COL_REP_DIUSULKAN_PJO = 23;

    public const COL_REP_DITINJAU = 24;

    public const COL_REP_DISETUJUI = 25;

    public const COL_REP_AKTUAL = 26;

    public const COL_DETEKSI_DEVIASI = 27;

    public const COL_INTERVENSI_DEVIASI = 28;

    public const COL_PREDIKSI_RISIKO = 29;

    public const COL_TERKAIT_HAZARD = 30;

    public const COL_TERKAIT_INSIDEN = 31;

    public const COL_EFEKTIVITAS_REKAYASA = 32;

    public const COL_NEXT_TODO = 33;

    public const COL_POTENSI_EFEKTIVITAS = 34;

    public const COL_PENGENDALIAN_EFEKTIVITAS = 35;

    public const COL_AKTIVITAS = 36;

    public const COL_TOTAL_RISIKO_SIGNIFIKAN = 37;

    public const COL_LINK_LIST_RISIKO_SIGNIFIKAN = 38;

    public const COL_JUMLAH_RISIKO_SIGNIFIKAN_TERCOVER = 39;

    public const COL_LINK_RISIKO_SIGNIFIKAN_TERCOVER = 40;

    public const TOTAL_COLUMNS = 41;

    /**
     * @return list<string>
     */
    public static function groupHeaders(): array
    {
        return [
            'NO',
            'SITE',
            'PERUSAHAAN',
            'SUMBER REKAYASA',
            'PELAKSANA REKAYASA',
            'PENGENDALIAN REKAYASA',
            'TANGGAL IDEATION',
            'KAJIAN TEKNIS',
            'PENGADAAN',
            'UJI COBA',
            'STANDARISASI',
            'REPLIKASI',
            'DETEKSI DEVIASI',
            'INTERVENSI DEVIASI',
            'PREDIKSI PENURUNAN TANGGA NILAI RISIKO',
            'TERKAIT HAZARD',
            'TERKAIT INSIDEN',
            'EFEKTIVITAS REKAYASA',
            'NEXT TO DO',
            'POTENSI PENINGKATAN LEVEL EFEKTIVITAS',
            'PENGENDALIAN REKAYASA (PENINGKATAN LEVEL EFEKTIVITAS)',
            'AKTIVITAS',
            'TOTAL RISIKO SIGNIFIKAN',
            'LINK LIST RISIKO SIGNIFIKAN',
            'JUMLAH RISIKO SIGNIFIKAN POTENSI TERCOVER REKAYASA',
            'LINK RISIKO SIGNIFIKAN POTENSI TERCOVER REKAYASA',
        ];
    }

    /**
     * @return list<string>
     */
    public static function leafHeaders(): array
    {
        return [
            'NO',
            'SITE',
            'PERUSAHAAN',
            'SUMBER REKAYASA',
            'PELAKSANA REKAYASA',
            'PENGENDALIAN REKAYASA',
            'TANGGAL IDEATION',
            'Due Date',
            'Status',
            'Evidence',
            'Due Date',
            'Status',
            'Evidence',
            'Due Date',
            'Status',
            'Evidence',
            'Due Date',
            'Status',
            'Evidence',
            'Due Date',
            'Total Populasi',
            'Satuan Rekayasa',
            'Target Replikasi by Komitmen',
            'Diusulkan Oleh PJO',
            'Ditinjau',
            'Disetujui',
            'Aktual Replikasi',
            'DETEKSI DEVIASI',
            'INTERVENSI DEVIASI',
            'PREDIKSI PENURUNAN TANGGA NILAI RISIKO',
            'TERKAIT HAZARD',
            'TERKAIT INSIDEN',
            'EFEKTIVITAS REKAYASA',
            'NEXT TO DO',
            'POTENSI PENINGKATAN LEVEL EFEKTIVITAS',
            'PENGENDALIAN REKAYASA (PENINGKATAN LEVEL EFEKTIVITAS)',
            'AKTIVITAS',
            'TOTAL RISIKO SIGNIFIKAN',
            'LINK LIST RISIKO SIGNIFIKAN',
            'JUMLAH RISIKO SIGNIFIKAN POTENSI TERCOVER REKAYASA',
            'LINK RISIKO SIGNIFIKAN POTENSI TERCOVER REKAYASA',
        ];
    }

    /**
     * Merge vertikal baris 1–2 untuk kolom tanpa sub-header.
     *
     * @return list{array{string, string}}
     */
    public static function verticalMergeRanges(): array
    {
        return [
            ['A1', 'A2'],
            ['B1', 'B2'],
            ['C1', 'C2'],
            ['D1', 'D2'],
            ['E1', 'E2'],
            ['F1', 'F2'],
            ['G1', 'G2'],
            ['AB1', 'AB2'],
            ['AC1', 'AC2'],
            ['AD1', 'AD2'],
            ['AE1', 'AE2'],
            ['AF1', 'AF2'],
            ['AG1', 'AG2'],
            ['AH1', 'AH2'],
            ['AI1', 'AI2'],
            ['AJ1', 'AJ2'],
            ['AK1', 'AK2'],
            ['AL1', 'AL2'],
            ['AM1', 'AM2'],
            ['AN1', 'AN2'],
            ['AO1', 'AO2'],
        ];
    }

    /**
     * @return list{string, string}
     */
    public static function lastColumnLetter(): string
    {
        return 'AO';
    }
}
