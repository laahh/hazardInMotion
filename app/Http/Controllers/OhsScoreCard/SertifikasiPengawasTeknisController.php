<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

/**
 * Parameter HSECT "Pemenuhan Sertifikasi Pengawas Teknis".
 *
 * Sumbernya competency_pengawas_teknis: 12.051 baris untuk 314 orang, karena
 * tiap orang punya satu baris per pasangan dokumen x izin kerja.
 *
 * Seluruh logikanya ada di kelas induk; di sini hanya yang membedakannya.
 * Lihat AbstractSertifikasiKompetensiController untuk rumus, band, dan alasan
 * kolom sertifikasi yang dipakai sebagai penanda bersertifikat.
 */
final class SertifikasiPengawasTeknisController extends AbstractSertifikasiKompetensiController
{
    protected function tabel(): string
    {
        return 'competency_pengawas_teknis';
    }

    protected function peran(): string
    {
        return 'Pengawas Teknis';
    }

    protected function slug(): string
    {
        return 'sertifikasi-pengawas-teknis';
    }
}
