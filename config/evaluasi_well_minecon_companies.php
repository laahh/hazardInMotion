<?php

declare(strict_types=1);

/**
 * Daftar perusahaan untuk breakdown Dimensi Perusahaan (Minecon + BC)
 * di modal Detail Total User Install (EvaluasiWell).
 *
 * Matching case-insensitive terhadap nama kanonik (setelah alias resolver).
 * Urutan: PT Berau Coal di atas, lalu mitra Minecon.
 */
return [
    'PT Berau Coal', // BC (termasuk alias PT Berau Coal Energy)
    'PT Bukit Makmur Mandiri Utama', // BUMA
    'PT Kaltim Diamond Coal', // KDC
    'PT Mutiara Tanjung Lestari', // MTL
    'PT Pamapersada Nusantara', // PAMA
    'PT Bumi Artlantis Raya', // BAR
    'PT Fajar Anugerah Dinamika', // FAD
    'PT Madhani Talatah Nusantara', // MTN
];
