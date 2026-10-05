<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

/**
 * Parameter HSECT "Pemenuhan Sertifikasi Tenaga Teknis".
 *
 * Sumbernya competency_tenaga_teknis: 6.043 baris untuk 3.069 orang.
 *
 * CATATAN KHUSUS TABEL INI: kolom sertifikasi_sesuai_dokumen bernilai 1 untuk
 * SELURUH barisnya, jadi kolom itu tidak membedakan siapa pun dan sengaja
 * tidak dipakai. Penanda bersertifikat tetap kolom sertifikasi, sama dengan
 * halaman pengawas teknis.
 *
 * Seluruh logikanya ada di kelas induk.
 */
final class SertifikasiTenagaTeknisController extends AbstractSertifikasiKompetensiController
{
    protected function tabel(): string
    {
        return 'competency_tenaga_teknis';
    }

    protected function peran(): string
    {
        return 'Tenaga Teknis';
    }

    protected function slug(): string
    {
        return 'sertifikasi-tenaga-teknis';
    }
}
