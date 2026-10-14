<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

/**
 * Definisi tampilan empat aturan "Penegakan Aturan & Alur Banned/Unban" untuk
 * halaman Ringkasan Roster: dipakai tile di kartu utama
 * (_roster-overview) dan modal detailnya (_roster-reg-terkini), supaya judul,
 * ambang, dan perlakuan tidak ditulis dua kali. Angka ambang dibaca dari
 * config dms_roster.reg_live.aturan — sama dengan yang dihitung
 * DmsRosterRegLiveService.
 */
final class DmsRosterAturanKartu
{
    /**
     * Setiap aturan: id (= sufiks id modal), ikon, warna (danger|warning),
     * judul, ambang, perlakuan (+ sub), catatan, dan item tab. Item tab:
     * kode daftar => [label tab, definisi, kolom nilai, kolom mulai, kolom selesai].
     *
     * @return list<array<string, mixed>>
     */
    public static function daftar(): array
    {
        /** @var array<string, int> $a */
        $a = config('dms_roster.reg_live.aturan', []);
        $onsiteMaks = $a['onsite_maks'] ?? 70;
        $beruntunMaks = $a['beruntun_maks'] ?? 13;
        $jamMaks = $a['jam_kerja_maks'] ?? 12;
        $lamaBanned = $a['onsite_banned_lama'] ?? 14;

        return [
            [
                'id' => 'onsite',
                'ikon' => 'solar:calendar-date-bold',
                'warna' => 'danger',
                'judul' => 'Pelanggaran periode kerja',
                'ambang' => '> '.$onsiteMaks.' hari',
                'perlakuan' => 'Banned '.$lamaBanned.' hari',
                'perlakuanSub' => '(cuti '.$lamaBanned.' hari)',
                'catatan' => 'Banned akan hari ke-'.($a['onsite_banned_hari_ke'] ?? 74).', dengan asumsi 1 hari perjalanan keluar mess & 1 hari induksi awal.',
                'item' => [
                    'onsite' => ['Periode kerja > '.$onsiteMaks.' hari', 'On-site (kerja + off pendek) lebih dari '.$onsiteMaks.' hari sejak cuti terakhir, sampai hari ini.', 'Periode kerja', 'Mulai on-site', 'Hari terakhir'],
                ],
            ],
            [
                'id' => 'beruntun',
                'ikon' => 'solar:users-group-rounded-bold',
                'warna' => 'danger',
                'judul' => 'Pelanggaran bekerja',
                'ambang' => '> '.$beruntunMaks.' hari berturut-turut',
                'perlakuan' => 'Banned 1 hari off',
                'perlakuanSub' => '',
                'catatan' => 'Banned hari ke-'.($a['beruntun_banned_hari_ke'] ?? 14).'.',
                'item' => [
                    'beruntun' => ['Bekerja > '.$beruntunMaks.' hari berturut-turut', 'Kerja setiap hari tanpa satu pun hari off, lebih dari '.$beruntunMaks.' hari sampai hari ini.', 'Berturut-turut', 'Mulai', 'Hari terakhir'],
                ],
            ],
            [
                'id' => 'jam12',
                'ikon' => 'solar:clock-circle-bold',
                'warna' => 'warning',
                'judul' => 'Waktu kerja',
                'ambang' => 'maksimal '.$jamMaks.' jam',
                'perlakuan' => 'Highlight warna',
                'perlakuanSub' => '(peringatan)',
                'catatan' => 'Akan mempengaruhi waktu istirahat hari berikutnya.',
                'item' => [
                    'jam12' => ['Waktu kerja > '.$jamMaks.' jam', 'Shift terakhir ('.($a['jam_kerja_hari_terkini'] ?? 3).' hari terakhir) lebih dari '.$jamMaks.' jam dari tap check-in sampai check-out gate.', 'Durasi', 'Check-in', 'Check-out'],
                ],
            ],
            [
                'id' => 'mapping',
                'ikon' => 'solar:document-text-bold',
                'warna' => 'warning',
                'judul' => 'Tidak sesuai mapping',
                'ambang' => 'roster perusahaan',
                'perlakuan' => 'Highlight warna',
                'perlakuanSub' => '(peringatan)',
                'catatan' => 'Perusahaan tidak sesuai komitmen plan design roster, waktu kerja & istirahat yang diajukan.',
                'item' => [
                    'map1' => ['MAP-1 · Melebihi blok roster', 'Blok kerja terakhir lebih panjang dari pola roster PT-nya, tapi belum '.($beruntunMaks + 1).' hari.', 'Blok kerja', 'Mulai blok', 'Akhir blok'],
                    'map2' => ['MAP-2 · Wajib OFF saat ganti shift', 'Khusus PAMA: pindah Pagi↔Malam tanpa diselingi off.', 'Blok kerja', 'Mulai blok', 'Akhir blok'],
                    'map3' => ['MAP-3 · Urutan shift terbalik', 'PT selain PAMA: shift Pagi muncul setelah Malam dalam satu blok.', 'Blok kerja', 'Mulai blok', 'Akhir blok'],
                ],
            ],
        ];
    }

    /**
     * Jumlah orang unik di seluruh tab satu aturan (satu orang bisa kena
     * lebih dari satu MAP). null bila data RFID tidak tersedia.
     *
     * @param  array<string, mixed>  $kartu  Satu elemen daftar().
     * @param  array<string, mixed>|null  $data  Hasil DmsRosterRegLiveService::ambil().
     */
    public static function jumlahOrang(array $kartu, ?array $data): ?int
    {
        if ($data === null) {
            return null;
        }

        $sid = [];
        foreach (array_keys($kartu['item']) as $kode) {
            foreach ($data['daftar'][$kode] ?? [] as $r) {
                $sid[$r['sid']] = true;
            }
        }

        return count($sid);
    }
}
