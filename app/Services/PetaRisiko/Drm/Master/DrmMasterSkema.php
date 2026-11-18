<?php

namespace App\Services\PetaRisiko\Drm\Master;

/**
 * Definisi tabel master mapping DRM 2.1 untuk halaman /peta-risiko-pja/master (generik: satu controller + view).
 *
 * Tiap tabel: [judul, kode sheet, tabel DB, kunci, kolom, kolom cari, urut, catatan].
 * Kolom: nama => [label, tipe, aturan validasi Laravel, opsi]. Tipe: int, teks, teks_panjang, bool, pilih.
 * opsi: 'kunci' (kunci utama yang diisi manual, tidak bisa diubah setelah dibuat), 'ri' (dropdown cari baris HIRA),
 *       'saran' => 'tabel.kolom' (dropdown saran dari nilai yang sudah ada, tetap boleh diketik baru),
 *       'isiAid' (memilih aktivitas mengisi ID aktivitas otomatis),
 *       'pilihan' => sumber pilihan ('kelompok' | 'grup' | 'tema' | daftar statis).
 */
final class DrmMasterSkema
{
    /** @return array<string, array<string, mixed>> */
    public static function semua(): array
    {
        $semua = [
            'baris' => [
                'judul' => 'Baris HIRA (baseline)', 'sheet' => 'M01', 'tabel' => 'drm_master_baris', 'kunci' => 'ri', 'kunciManual' => true,
                'catatan' => 'Baseline: tiap baris = aktivitas × sub × bahaya-risiko. Nonaktifkan untuk mengeluarkan baris dari baseline.',
                'kolom' => [
                    'ri' => ['ID baris', 'int', ['required', 'integer', 'min:0'], ['kunci' => true]],
                    'aid' => ['ID aktivitas', 'int', ['required', 'integer', 'min:0'], []],
                    'aktivitas' => ['Aktivitas', 'teks', ['required', 'string', 'max:500'], ['saran' => 'drm_master_aktivitas.aktivitas', 'isiAid' => true]],
                    'sub' => ['Sub aktivitas', 'teks', ['nullable', 'string', 'max:500'], ['saran' => 'drm_master_baris.sub']],
                    'bahaya' => ['Bahaya-risiko', 'teks_panjang', ['required', 'string', 'max:1000'], []],
                    'kb' => ['Kategori bahaya', 'pilih', ['nullable', 'in:Significant,High,Medium,Low'], ['pilihan' => ['Significant', 'High', 'Medium', 'Low']]],
                    'umum' => ['Bahaya umum', 'bool', ['boolean'], []],
                    'idh' => ['ID HIRA asli', 'teks', ['nullable', 'string', 'max:200', 'regex:/^[0-9, ]*$/'], []],
                    'aktif' => ['Aktif (masuk baseline)', 'bool', ['boolean'], []],
                    'catatan' => ['Catatan', 'teks', ['nullable', 'string', 'max:500'], []],
                ],
                'cari' => ['aktivitas', 'sub', 'bahaya', 'ri'], 'urut' => 'ri',
            ],
            'kelompok' => [
                'judul' => 'Kelompok aktivitas', 'sheet' => 'M02', 'tabel' => 'drm_master_kelompok', 'kunci' => 'kode', 'kunciManual' => true,
                'catatan' => 'Regex dipakai untuk mengelompokkan teks aktivitas OBS/OAK (kecocokan pertama menang, urut nomor urutan). Kelompok baris HIRA diatur di tab Aktivitas → kelompok.',
                'kolom' => [
                    'kode' => ['Kode', 'teks', ['required', 'string', 'max:10', 'regex:/^K[0-9]+$/'], ['kunci' => true]],
                    'nama' => ['Nama kelompok', 'teks', ['required', 'string', 'max:200'], []],
                    'regex' => ['Regex teks OBS/OAK', 'teks_panjang', ['nullable', 'string', 'max:2000'], []],
                    'urutan' => ['Urutan', 'int', ['required', 'integer', 'min:0', 'max:999'], []],
                ],
                'cari' => ['kode', 'nama', 'regex'], 'urut' => 'urutan',
            ],
            'aktivitas' => [
                'judul' => 'Aktivitas → kelompok', 'sheet' => 'M03', 'tabel' => 'drm_master_aktivitas', 'kunci' => 'aid', 'kunciManual' => true,
                'catatan' => 'Kelompok aktivitas setiap baris HIRA mengikuti aktivitasnya di sini.',
                'kolom' => [
                    'aid' => ['ID aktivitas', 'int', ['required', 'integer', 'min:0'], ['kunci' => true]],
                    'aktivitas' => ['Aktivitas HIRA', 'teks', ['required', 'string', 'max:500'], ['saran' => 'drm_master_baris.aktivitas']],
                    'kelompok' => ['Kelompok aktivitas', 'pilih', ['required', 'string', 'max:10'], ['pilihan' => 'kelompok']],
                ],
                'cari' => ['aktivitas', 'kelompok', 'aid'], 'urut' => 'aktivitas',
            ],
            'grup' => [
                'judul' => 'Grup lokasi', 'sheet' => 'M04', 'tabel' => 'drm_master_grup', 'kunci' => 'gid', 'kunciManual' => true,
                'catatan' => 'Grup lokasi HIRA. Setiap detail lokasi masuk tepat satu grup dan mewarisi baris HIRA grupnya.',
                'kolom' => [
                    'gid' => ['ID grup', 'int', ['required', 'integer', 'min:1', 'max:9999'], ['kunci' => true]],
                    'nama' => ['Nama grup', 'teks', ['required', 'string', 'max:120'], []],
                    'aktif' => ['Aktif', 'bool', ['boolean'], []],
                ],
                'cari' => ['nama', 'gid'], 'urut' => 'gid',
            ],
            'grup_baris' => [
                'judul' => 'Grup → baris HIRA', 'sheet' => 'M05', 'tabel' => 'drm_master_grup_baris', 'kunci' => 'id', 'kunciManual' => false,
                'catatan' => 'Baris HIRA yang berlaku di tiap grup lokasi. Baris yang tidak ada di grup suatu detail lokasi tidak masuk baseline detail lokasi itu (gerbang).',
                'kolom' => [
                    'gid' => ['Grup lokasi', 'pilih', ['required', 'integer'], ['pilihan' => 'grup']],
                    'ri' => ['ID baris HIRA', 'int', ['required', 'integer', 'exists:drm_master_baris,ri'], ['ri' => true]],
                ],
                'cari' => ['gid', 'ri'], 'urut' => 'gid',
            ],
            'dl' => [
                'judul' => 'Detail lokasi → grup', 'sheet' => 'M06', 'tabel' => 'drm_master_dl', 'kunci' => 'id', 'kunciManual' => false,
                'catatan' => 'Nama site / lokasi / detail lokasi harus sama dengan di SAP (huruf besar-kecil dan spasi ganda diabaikan).',
                'kolom' => [
                    'site' => ['Site', 'teks', ['required', 'string', 'max:60'], ['saran' => 'drm_master_dl.site']],
                    'lokasi' => ['Lokasi', 'teks', ['required', 'string', 'max:200'], ['saran' => 'drm_master_dl.lokasi']],
                    'detail' => ['Detail lokasi', 'teks', ['required', 'string', 'max:300'], []],
                    'gid' => ['Grup lokasi', 'pilih', ['required', 'integer'], ['pilihan' => 'grup']],
                    'aktif' => ['Aktif', 'bool', ['boolean'], []],
                ],
                'cari' => ['site', 'lokasi', 'detail'], 'urut' => 'site',
            ],
            'lokasi_pja' => [
                'judul' => 'Lokasi → PJA', 'sheet' => 'M07/M08', 'tabel' => 'drm_master_lokasi_pja', 'kunci' => 'id', 'kunciManual' => false,
                'catatan' => 'PJA melekat di level lokasi; semua detail lokasinya ikut. Nama PJA harus sama dengan nama di master PJA (m_pja).',
                'kolom' => [
                    'site' => ['Site', 'teks', ['required', 'string', 'max:60'], ['saran' => 'drm_master_dl.site']],
                    'lokasi' => ['Lokasi', 'teks', ['required', 'string', 'max:200'], ['saran' => 'drm_master_dl.lokasi']],
                    'nama_pja' => ['Nama PJA', 'teks', ['required', 'string', 'max:200'], ['saran' => 'drm_master_lokasi_pja.nama_pja']],
                    'aktif' => ['Aktif', 'bool', ['boolean'], []],
                ],
                'cari' => ['site', 'lokasi', 'nama_pja'], 'urut' => 'site',
            ],
            'label' => [
                'judul' => 'Label SAP → baris', 'sheet' => 'M09', 'tabel' => 'drm_master_label', 'kunci' => 'id', 'kunciManual' => false,
                'catatan' => 'Laporan Hazard/Inspeksi berlabel ini mengidentifikasi baris HIRA terpilih (hanya bila baris itu milik grup detail lokasi laporan).',
                'kolom' => [
                    'ketidaksesuaian' => ['Ketidaksesuaian', 'teks', ['required', 'string', 'max:300'], ['saran' => 'drm_master_label.ketidaksesuaian']],
                    'subketidaksesuaian' => ['Subketidaksesuaian', 'teks', ['required', 'string', 'max:300'], ['saran' => 'drm_master_label.subketidaksesuaian']],
                    'ri' => ['ID baris HIRA', 'int', ['required', 'integer', 'exists:drm_master_baris,ri'], ['ri' => true]],
                    'sumber' => ['Sumber', 'teks', ['nullable', 'string', 'max:100'], ['saran' => 'drm_master_label.sumber']],
                    'aktif' => ['Aktif', 'bool', ['boolean'], []],
                ],
                'cari' => ['ketidaksesuaian', 'subketidaksesuaian', 'ri'], 'urut' => 'ketidaksesuaian',
            ],
            'teks' => [
                'judul' => 'Teks OBS/OAK → baris', 'sheet' => 'M11/M12', 'tabel' => 'drm_master_teks', 'kunci' => 'id', 'kunciManual' => false,
                'catatan' => 'OBS: teks aktivitas observasi persis. OAK: "aktivitas / sub aktivitas" persis. Hasil OAK "Stop" = gap OAK Stop.',
                'kolom' => [
                    'jenis' => ['Jenis', 'pilih', ['required', 'in:OBS,OAK'], ['pilihan' => ['OBS', 'OAK']]],
                    'teks' => ['Teks aktivitas', 'teks', ['required', 'string', 'max:500'], ['saran' => 'drm_master_teks.teks']],
                    'ri' => ['ID baris HIRA', 'int', ['required', 'integer', 'exists:drm_master_baris,ri'], ['ri' => true]],
                    'aktif' => ['Aktif', 'bool', ['boolean'], []],
                ],
                'cari' => ['teks', 'ri', 'jenis'], 'urut' => 'teks',
            ],
            'oak_aktivitas' => [
                'judul' => 'Aktivitas OAK → aktivitas HIRA', 'sheet' => 'OAK', 'tabel' => 'drm_master_oak_aktivitas', 'kunci' => 'id', 'kunciManual' => false,
                'catatan' => 'Laporan OAK dengan aktivitas ini (sub aktivitas apa pun) membuat SEMUA bahaya-risiko di aktivitas HIRA terpilih berlaku untuk laporan itu, di samping label M12. Baris baru tertangkap bila ada butir checklist bertema sama. Nonaktifkan tautan yang terlalu luas.',
                'kolom' => [
                    'aktivitas_oak' => ['Aktivitas OAK', 'teks', ['required', 'string', 'max:300'], ['saran' => 'drm_master_oak_aktivitas.aktivitas_oak']],
                    'aktivitas_hira' => ['Aktivitas HIRA', 'teks', ['required', 'string', 'max:500'], ['saran' => 'drm_master_baris.aktivitas']],
                    'aktif' => ['Aktif', 'bool', ['boolean'], []],
                    'catatan' => ['Catatan', 'teks', ['nullable', 'string', 'max:500'], []],
                ],
                'cari' => ['aktivitas_oak', 'aktivitas_hira'], 'urut' => 'aktivitas_oak',
            ],
            'tema' => [
                'judul' => 'Tema checklist', 'sheet' => 'M15', 'tabel' => 'drm_master_tema', 'kunci' => 'id', 'kunciManual' => true,
                'catatan' => 'Regex teks dipakai untuk butir checklist OAK dan teks OBS. Pasangan tema → baris diatur di tab Tema → baris.',
                'kolom' => [
                    'id' => ['ID tema', 'int', ['required', 'integer', 'min:1', 'max:999'], ['kunci' => true]],
                    'nama' => ['Nama tema', 'teks', ['required', 'string', 'max:120'], []],
                    'regex_teks' => ['Regex teks checklist/OBS', 'teks_panjang', ['required', 'string', 'max:2000'], []],
                    'regex_bahaya' => ['Regex teks bahaya (acuan)', 'teks_panjang', ['required', 'string', 'max:2000'], []],
                ],
                'cari' => ['nama', 'regex_teks'], 'urut' => 'id',
            ],
            'tema_baris' => [
                'judul' => 'Tema → baris', 'sheet' => 'M16', 'tabel' => 'drm_master_tema_baris', 'kunci' => 'id', 'kunciManual' => false,
                'catatan' => 'Baris diidentifikasi lewat tema bila kelompok aktivitas checklist/OBS sama dengan kelompok baris, atau baris itu bahaya umum.',
                'kolom' => [
                    'tema_id' => ['Tema', 'pilih', ['required', 'integer'], ['pilihan' => 'tema']],
                    'ri' => ['ID baris HIRA', 'int', ['required', 'integer', 'exists:drm_master_baris,ri'], ['ri' => true]],
                ],
                'cari' => ['tema_id', 'ri'], 'urut' => 'tema_id',
            ],
        ];
        if (! self::adaTabel('drm_master_oak_aktivitas')) {
            unset($semua['oak_aktivitas']);
        }
        // Master satu tabel: tiga kolom label Item Sumber di baris HIRA (bila sudah dimigrasi).
        if (self::adaKolom('drm_master_baris', 'item_sumber_observasi')) {
            $kolom = $semua['baris']['kolom'];
            $label = [
                'item_sumber_observasi' => ['Item Sumber Observasi (label master)', 'teks_panjang', ['nullable', 'string', 'max:30000'], ['satuBaris' => true]],
                'item_sumber_oak' => ['Item Sumber OAK (label master)', 'teks_panjang', ['nullable', 'string', 'max:60000'], ['satuBaris' => true]],
                'item_sumber_ketidaksesuaian' => ['Item Sumber Ketidaksesuaian (label master)', 'teks_panjang', ['nullable', 'string', 'max:60000'], ['satuBaris' => true]],
            ];
            $posisi = array_search('bahaya', array_keys($kolom), true) + 1;
            $semua['baris']['kolom'] = array_slice($kolom, 0, $posisi, true) + $label + array_slice($kolom, $posisi, null, true);
            $semua['baris']['judul'] = 'Master HIRA ↔ SAP (per baris)';
            $semua['baris']['catatan'] = 'Satu baris = aktivitas × sub × bahaya-risiko + label SAP yang menangkapnya. Kolom Item Sumber: satu label per baris teks — '
                .'Observasi = jenis kegiatan; OAK = "aktivitas / sub aktivitas"; Ketidaksesuaian = "ketidaksesuaian / sub ketidaksesuaian".';
            $semua['baris']['cari'] = [...$semua['baris']['cari'], 'item_sumber_observasi', 'item_sumber_oak', 'item_sumber_ketidaksesuaian'];
        }

        return $semua;
    }

    /** @return array<string, mixed>|null */
    public static function satu(string $kode): ?array
    {
        return self::semua()[$kode] ?? null;
    }

    private static function adaKolom(string $tabel, string $kolom): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn($tabel, $kolom);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Tabel dari migrasi terpisah: tab hanya tampil bila tabelnya sudah dimigrasi. */
    private static function adaTabel(string $tabel): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($tabel);
        } catch (\Throwable) {
            return false;
        }
    }
}
