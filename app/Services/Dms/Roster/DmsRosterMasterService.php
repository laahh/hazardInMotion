<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Throwable;

/**
 * Master Roster: upload jadwal kerja per karyawan dan persetujuannya.
 *
 * Format file "ROSTER KERJA KARYAWAN" (sama dengan template()):
 *   baris 1   judul
 *   baris 2   "Tanggal Awal Periode:" <tanggal>   … legenda kode di kanan
 *   baris 3   "Departemen / Site:" <teks>
 *   baris 4–6 judul kolom: No | SID | Nama Karyawan | Perusahaan | Jabatan | Site,
 *             lalu satu kolom per hari: baris 4 = tanggal (01, 02, …),
 *             baris 5 = nama hari, baris 6 = bulan
 *   baris 7+  satu karyawan per baris, satu kode per hari
 *
 * Pembaca tidak bergantung pada nomor baris/kolom persis: baris judul dicari
 * lewat sel "SID", kolom identitas lewat teks judulnya, dan tanggal tiap
 * kolom hari = Tanggal Awal Periode + urutan kolom (dicek terhadap angka
 * tanggal di judulnya). Sel kosong = tidak dijadwalkan.
 *
 * File ditolak utuh bila ada satu saja kesalahan, supaya roster yang masuk
 * approval selalu bersih; daftar kesalahannya dikembalikan.
 */
final class DmsRosterMasterService
{
    /**
     * kode => [keterangan, warna isi (RGB), jam check-in bawaan, jam check-out bawaan].
     * Jam null = bukan hari kerja. Dn = hari ke-n blok Day (06:00–18:00),
     * Nn = hari ke-n blok Night (18:00–06:00) — jam dari user 2026-10-26.
     * D1–D13 & N1–N7 menggantikan DS/NS (permintaan user 2026-10-26); kode OF
     * (Office) dihapus. Kode lama DS/NS tidak lagi diterima saat upload.
     */
    public const KODE = [
        'D1' => ['Day 1', 'FFE699', '06:00', '18:00'],
        'D2' => ['Day 2', 'FFE699', '06:00', '18:00'],
        'D3' => ['Day 3', 'FFE699', '06:00', '18:00'],
        'D4' => ['Day 4', 'FFE699', '06:00', '18:00'],
        'D5' => ['Day 5', 'FFE699', '06:00', '18:00'],
        'D6' => ['Day 6', 'FFE699', '06:00', '18:00'],
        'D7' => ['Day 7', 'FFE699', '06:00', '18:00'],
        'D8' => ['Day 8', 'FFE699', '06:00', '18:00'],
        'D9' => ['Day 9', 'FFE699', '06:00', '18:00'],
        'D10' => ['Day 10', 'FFE699', '06:00', '18:00'],
        'D11' => ['Day 11', 'FFE699', '06:00', '18:00'],
        'D12' => ['Day 12', 'FFE699', '06:00', '18:00'],
        'D13' => ['Day 13', 'FFE699', '06:00', '18:00'],
        'N1' => ['Night 1', '9DC3E6', '18:00', '06:00'],
        'N2' => ['Night 2', '9DC3E6', '18:00', '06:00'],
        'N3' => ['Night 3', '9DC3E6', '18:00', '06:00'],
        'N4' => ['Night 4', '9DC3E6', '18:00', '06:00'],
        'N5' => ['Night 5', '9DC3E6', '18:00', '06:00'],
        'N6' => ['Night 6', '9DC3E6', '18:00', '06:00'],
        'N7' => ['Night 7', '9DC3E6', '18:00', '06:00'],
        'OFF' => ['Off / libur', 'D9D9D9', null, null],
        'CT' => ['Cuti', 'F4B183', null, null],
        'S' => ['Sakit', 'FF9999', null, null],
        'I' => ['Izin', 'D9B3FF', null, null],
    ];

    /** Judul kolom identitas => kunci internal. Tiga kolom jam opsional. */
    private const KOLOM = [
        'no' => 'No',
        'sid' => 'SID',
        'nama' => 'Nama Karyawan',
        'perusahaan' => 'Perusahaan',
        'jabatan' => 'Jabatan',
        'site' => 'Site',
        'jam_masuk' => 'Jam Kerja Awal',
        'jam_keluar' => 'Jam Kerja Akhir',
        'total' => 'Total Jam Kerja',
    ];

    private const KOLOM_OPSIONAL = ['no', 'jam_masuk', 'jam_keluar', 'total'];

    /** Judul lama yang tetap dikenali (file dari template sebelumnya). */
    private const ALIAS_KOLOM = [
        'jam_masuk' => ['jam check-in'],
        'jam_keluar' => ['jam check-out'],
    ];

    /** Kolom yang tidak ditulis di template (tetap dibaca bila ada). Total Jam Kerja kembali ditulis sejak 28 Okt 2026. */
    private const KOLOM_TANPA_TEMPLATE = [];

    private const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Satu file = maksimal setahun (template bisa Januari–Desember sekaligus). */
    public const MAKS_HARI = 366;

    public const MAKS_BULAN = 12;

    private const MAKS_GALAT = 50;

    /**
     * Baca & validasi isi sheet (hasil toArray()). Murni, tanpa I/O.
     *
     * @param  list<list<mixed>>  $rows
     * @return array{galat: list<string>, tanggal: list<string>, departemen_site: ?string,
     *               karyawan: list<array{sid:string,nama:?string,perusahaan:?string,jabatan:?string,site:?string,
     *                                    jam:?array{masuk:string,keluar:string,menit:int},
     *                                    jadwal:array<string,array{kode:string,masuk:?string,keluar:?string,menit:?int}>}>}
     */
    public function bacaSheet(array $rows): array
    {
        $rows = array_map('array_values', array_values($rows));
        $kosong = ['galat' => [], 'tanggal' => [], 'departemen_site' => null, 'karyawan' => []];

        // 1. Baris judul kolom = baris pertama yang punya sel "SID".
        $barisJudul = null;
        foreach (array_slice($rows, 0, 20) as $i => $row) {
            foreach ($row as $sel) {
                if (strcasecmp(trim((string) $sel), 'SID') === 0) {
                    $barisJudul = $i;
                    break 2;
                }
            }
        }
        if ($barisJudul === null) {
            return ['galat' => ['Judul kolom "SID" tidak ditemukan. Gunakan template dari tombol Download Template.']] + $kosong;
        }

        $judul = $rows[$barisJudul];
        $kolom = [];
        foreach ($judul as $c => $sel) {
            $teks = mb_strtolower(trim((string) $sel));
            foreach (self::KOLOM as $kunci => $nama) {
                $dikenal = [mb_strtolower($nama), ...(self::ALIAS_KOLOM[$kunci] ?? [])];
                if (in_array($teks, $dikenal, true) && ! isset($kolom[$kunci])) {
                    $kolom[$kunci] = $c;
                }
            }
        }
        $galat = [];
        foreach (self::KOLOM as $kunci => $nama) {
            if (! in_array($kunci, self::KOLOM_OPSIONAL, true) && ! isset($kolom[$kunci])) {
                $galat[] = 'Baris '.($barisJudul + 1).': kolom "'.$nama.'" tidak ditemukan.';
            }
        }

        // 2. Tanggal Awal Periode & Departemen / Site di atas baris judul.
        $awal = null;
        $departemen = null;
        for ($i = 0; $i < $barisJudul; $i++) {
            foreach ($rows[$i] as $c => $sel) {
                $label = mb_strtolower(trim((string) $sel));
                if (str_starts_with($label, 'tanggal awal periode')) {
                    $nilai = $this->selSesudah($rows[$i], $c);
                    $awal = $nilai === null ? null : $this->tanggal($nilai);
                    // Kosong boleh bila judul kolom hari berisi tanggal lengkap (dicek di langkah 3).
                    if ($nilai !== null && $awal === null) {
                        $galat[] = 'Baris '.($i + 1).': "Tanggal Awal Periode" berisi "'.trim((string) $nilai).'", bukan tanggal (pakai DD/MM/YYYY).';
                    }
                } elseif (str_starts_with($label, 'departemen')) {
                    $nilai = $this->selSesudah($rows[$i], $c);
                    $departemen = $nilai === null ? null : mb_substr(trim((string) $nilai), 0, 255);
                }
            }
        }
        // 3. Kolom hari: sesudah kolom identitas terakhir (Site, atau Total Jam
        // Kerja bila kolom jam dipakai), berhenti di judul kosong pertama.
        // Judul tiap kolom boleh berupa angka tanggal (01, 1 — tanggalnya =
        // Tanggal Awal Periode + urutan kolom) ATAU tanggal Excel asli (nomor
        // seri / rumus seperti =D2+1 yang diformat "dd", atau teks tanggal).
        // Keduanya wajib berurutan hari demi hari.
        $hari = [];
        if (isset($kolom['site'])) {
            $cursor = $awal === null ? null : CarbonImmutable::parse($awal);
            for ($c = max($kolom) + 1; $c < count($judul); $c++) {
                $sel = $judul[$c];
                $teksSel = trim((string) $sel);
                if ($teksSel === '') {
                    break;
                }

                $tgl = $this->tanggalKolom($sel, $cursor);
                if ($tgl === false) {
                    break;  // bukan judul tanggal: akhir kolom hari
                }
                if ($tgl === null) {
                    $galat[] = 'Baris '.($barisJudul + 1).' kolom '.$this->kolom($c).': judul "'.$teksSel.'" berupa angka tanggal, '
                        .'tetapi "Tanggal Awal Periode" kosong — isi tanggal awal atau pakai tanggal lengkap di judul kolom.';
                    break;
                }
                if ($cursor !== null && $tgl !== $cursor->toDateString()) {
                    $galat[] = 'Baris '.($barisJudul + 1).' kolom '.$this->kolom($c).': tanggal '.CarbonImmutable::parse($tgl)->format('d/m/Y')
                        .' tidak berurutan (seharusnya '.$cursor->format('d/m/Y').').';
                    break;
                }
                $hari[$c] = $tgl;
                $cursor = CarbonImmutable::parse($tgl)->addDay();
            }
            if ($hari === [] && $galat === []) {
                $galat[] = 'Tidak ada kolom tanggal sesudah kolom "'.self::KOLOM[array_search(max($kolom), $kolom, true)].'".';
            }
            if (count($hari) > self::MAKS_HARI) {
                $galat[] = 'Satu file maksimal '.self::MAKS_HARI.' hari; file ini berisi '.count($hari).' hari.';
            }
            // Tanggal awal yang diisi harus sama dengan kolom hari pertama.
            if ($hari !== [] && $awal !== null && reset($hari) !== $awal && $galat === []) {
                $galat[] = 'Tanggal Awal Periode '.CarbonImmutable::parse($awal)->format('d/m/Y').' tidak sama dengan kolom tanggal pertama '
                    .CarbonImmutable::parse((string) reset($hari))->format('d/m/Y').'.';
            }
        }
        if ($galat !== []) {
            return ['galat' => $galat] + $kosong;
        }

        // 4. Baris karyawan: semua baris sesudah judul yang SID-nya terisi
        // (baris nama hari & bulan otomatis terlewati karena SID-nya kosong).
        $karyawan = [];
        $sidTerpakai = [];
        $teks = static fn ($v): ?string => ($t = trim((string) ($v ?? ''))) === '' ? null : $t;
        foreach (array_slice($rows, $barisJudul + 1, null, true) as $i => $row) {
            $no = $i + 1;
            $sid = mb_strtoupper(trim((string) ($row[$kolom['sid']] ?? '')));
            if ($sid === '') {
                continue;
            }
            if (! preg_match('/^[A-Z0-9]{1,20}$/', $sid)) {
                $galat[] = 'Baris '.$no.': SID "'.$sid.'" tidak valid (huruf/angka, maks 20).';

                continue;
            }
            if (isset($sidTerpakai[$sid])) {
                $galat[] = 'Baris '.$no.': SID '.$sid.' sudah ada di baris '.$sidTerpakai[$sid].'.';

                continue;
            }
            $sidTerpakai[$sid] = $no;

            $jamOrang = $this->jamKaryawan($row, $kolom, $no, $sid, $galat);

            $jadwal = [];
            foreach ($hari as $c => $tgl) {
                $isi = mb_strtoupper(trim((string) ($row[$c] ?? '')));
                if ($isi === '') {
                    continue;
                }
                if (! isset(self::KODE[$isi])) {
                    $galat[] = 'Baris '.$no.' ('.$sid.') tanggal '.CarbonImmutable::parse($tgl)->format('d/m/Y').': kode "'.$isi.'" tidak dikenal '
                        .'(pakai '.implode(', ', array_keys(self::KODE)).').';

                    continue;
                }
                $jadwal[$tgl] = $this->jamHari($isi, $jamOrang);
            }

            $karyawan[] = [
                'sid' => $sid,
                'nama' => $teks($row[$kolom['nama']] ?? null),
                'perusahaan' => $teks($row[$kolom['perusahaan']] ?? null),
                'jabatan' => $teks($row[$kolom['jabatan']] ?? null),
                'site' => $teks($row[$kolom['site']] ?? null),
                'jam' => $jamOrang,
                'jadwal' => $jadwal,
            ];

            if (count($galat) >= self::MAKS_GALAT) {
                $galat[] = 'Pemeriksaan dihentikan setelah '.self::MAKS_GALAT.' kesalahan.';
                break;
            }
        }

        if ($karyawan === [] && $galat === []) {
            $galat[] = 'File tidak berisi baris karyawan (kolom SID kosong semua).';
        }

        return [
            'galat' => $galat,
            'tanggal' => array_values($hari),
            'departemen_site' => $departemen,
            'karyawan' => $galat === [] ? $karyawan : [],
        ];
    }

    /**
     * Simpan upload baru berstatus PENDING beserta jadwalnya.
     *
     * @return array{upload: ?DmsRosterMasterUpload, galat: list<string>}
     */
    public function simpan(UploadedFile $file, string $judul, ?string $catatan, string $oleh): array
    {
        try {
            // File setahun (366 kolom × ratusan karyawan) bisa ratusan ribu sel:
            // baca tanpa gaya sel, dan beri ruang memori/waktu lebih.
            @ini_set('memory_limit', '1024M');
            @set_time_limit(300);
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $rows = $reader->load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        } catch (Throwable $e) {
            return ['upload' => null, 'galat' => ['File tidak bisa dibaca sebagai Excel: '.$e->getMessage()]];
        }

        $hasil = $this->bacaSheet($rows);
        if ($hasil['galat'] !== []) {
            return ['upload' => null, 'galat' => $hasil['galat']];
        }

        $jumlahJadwal = array_sum(array_map(static fn (array $k): int => count($k['jadwal']), $hasil['karyawan']));
        if ($jumlahJadwal === 0) {
            return ['upload' => null, 'galat' => ['Semua sel jadwal kosong — tidak ada yang disimpan.']];
        }

        // Kolom menit_total_kerja baru ada setelah migrasi 2026_10_28_100000
        // dijalankan (manual); sebelum itu total jam tidak disimpan.
        $adaTotal = self::adaKolomTotal();

        $upload = DB::transaction(function () use ($file, $judul, $catatan, $oleh, $hasil, $jumlahJadwal, $adaTotal): DmsRosterMasterUpload {
            $upload = DmsRosterMasterUpload::create([
                'judul' => $judul,
                'periode_mulai' => $hasil['tanggal'][0],
                'periode_selesai' => $hasil['tanggal'][count($hasil['tanggal']) - 1],
                'departemen_site' => $hasil['departemen_site'],
                'nama_file' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path_file' => '',
                'jumlah_karyawan' => count($hasil['karyawan']),
                'jumlah_jadwal' => $jumlahJadwal,
                'status' => DmsRosterMasterUpload::STATUS_PENDING,
                'catatan' => $catatan,
                'diupload_oleh' => $oleh,
            ]);

            $baris = [];
            foreach ($hasil['karyawan'] as $k) {
                foreach ($k['jadwal'] as $tgl => $h) {
                    $baris[] = [
                        'upload_id' => $upload->id,
                        'kode_sid' => $k['sid'],
                        'nama' => $k['nama'] === null ? null : mb_substr($k['nama'], 0, 150),
                        'perusahaan' => $k['perusahaan'] === null ? null : mb_substr($k['perusahaan'], 0, 255),
                        'jabatan' => $k['jabatan'] === null ? null : mb_substr($k['jabatan'], 0, 150),
                        'site' => $k['site'] === null ? null : mb_substr($k['site'], 0, 60),
                        'tanggal' => $tgl,
                        'kode' => $h['kode'],
                        'jam_masuk' => $h['masuk'],
                        'jam_keluar' => $h['keluar'],
                        'menit_kerja' => $h['menit'],
                    ] + ($adaTotal ? ['menit_total_kerja' => $h['total'] ?? null] : []);
                    if (count($baris) >= 1000) {
                        DB::table('dms_roster_master_jadwal')->insert($baris);
                        $baris = [];
                    }
                }
            }
            if ($baris !== []) {
                DB::table('dms_roster_master_jadwal')->insert($baris);
            }

            $path = $file->storeAs('roster-master/'.$upload->id, 'roster.'.($file->getClientOriginalExtension() ?: 'xlsx'), 'local');
            $upload->update(['path_file' => (string) $path]);

            return $upload;
        });

        return ['upload' => $upload, 'galat' => []];
    }

    /**
     * Setujui atau tolak upload yang masih PENDING.
     */
    /**
     * @param  array{path:string,sumber:string}|null  $ttd  Hasil simpanTtd(); wajib untuk persetujuan.
     */
    public function review(DmsRosterMasterUpload $upload, bool $setuju, ?string $catatan, string $oleh, ?array $ttd = null): void
    {
        if (! $upload->isPending()) {
            throw new \RuntimeException('Roster ini sudah direview ('.$upload->status.').');
        }
        if ($setuju && $ttd === null) {
            throw new \RuntimeException('Persetujuan wajib disertai tanda tangan.');
        }

        $upload->update([
            'status' => $setuju ? DmsRosterMasterUpload::STATUS_APPROVED : DmsRosterMasterUpload::STATUS_REJECTED,
            'direview_oleh' => $oleh,
            'direview_pada' => now(),
            'catatan_review' => $catatan,
            'ttd_path' => $ttd['path'] ?? null,
            'ttd_sumber' => $ttd['sumber'] ?? null,
        ]);
    }

    /** Batas ukuran gambar tanda tangan (byte), digambar maupun di-upload. */
    public const MAKS_TTD_BYTE = 2 * 1024 * 1024;

    /**
     * Simpan tanda tangan approver: PNG dari canvas signature_pad (data URL)
     * atau file gambar yang di-upload. Isi file dicek benar-benar gambar
     * PNG/JPEG (bukan sekadar ekstensinya).
     *
     * @return array{path:string,sumber:string}
     */
    public function simpanTtd(DmsRosterMasterUpload $upload, ?string $dataUrl, ?UploadedFile $file, bool $digambar = false): array
    {
        if ($file !== null) {
            $isi = (string) file_get_contents($file->getRealPath());
            $sumber = $digambar ? 'GAMBAR' : 'UPLOAD';
        } else {
            if ($dataUrl === null || preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) !== 1) {
                throw new \RuntimeException('Tanda tangan belum digambar.');
            }
            $isi = (string) base64_decode($m[1], true);
            $sumber = 'GAMBAR';
        }

        if ($isi === '' || strlen($isi) > self::MAKS_TTD_BYTE) {
            throw new \RuntimeException('Gambar tanda tangan kosong atau lebih dari 2 MB.');
        }
        $info = @getimagesizefromstring($isi);
        $ekstensi = match ($info[2] ?? null) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            default => null,
        };
        if ($ekstensi === null) {
            throw new \RuntimeException('Tanda tangan harus berupa gambar PNG atau JPG.');
        }

        $path = 'roster-master/'.$upload->id.'/ttd-approval.'.$ekstensi;
        Storage::disk('local')->put($path, $isi);

        return ['path' => $path, 'sumber' => $sumber];
    }

    /**
     * Hapus upload (hanya yang belum disetujui) beserta jadwal & filenya.
     */
    public function hapus(DmsRosterMasterUpload $upload): void
    {
        if ($upload->status === DmsRosterMasterUpload::STATUS_APPROVED) {
            throw new \RuntimeException('Roster yang sudah disetujui tidak bisa dihapus.');
        }

        DB::transaction(static function () use ($upload): void {
            DB::table('dms_roster_master_jadwal')->where('upload_id', $upload->id)->delete();
            $upload->delete();
        });
        Storage::disk('local')->deleteDirectory('roster-master/'.$upload->id);
    }

    /**
     * Jumlah jadwal di upload ini yang akan menimpa jadwal dari roster lain
     * yang sudah APPROVED (SID + tanggal sama) — informasi untuk approver.
     */
    public function jumlahTumpang(DmsRosterMasterUpload $upload): int
    {
        return (int) DB::table('dms_roster_master_jadwal as j')
            ->where('j.upload_id', $upload->id)
            ->whereExists(static fn ($q) => $q->from('dms_roster_master_jadwal as j2')
                ->join('dms_roster_master_upload as u2', 'u2.id', '=', 'j2.upload_id')
                ->where('u2.status', DmsRosterMasterUpload::STATUS_APPROVED)
                ->where('u2.id', '<>', $upload->id)
                ->whereColumn('j2.kode_sid', 'j.kode_sid')
                ->whereColumn('j2.tanggal', 'j.tanggal'))
            ->count();
    }

    /**
     * Jadwal satu halaman karyawan untuk tampilan grid (SID × tanggal).
     *
     * @return array{karyawan: list<array{sid:string,nama:?string,perusahaan:?string,jabatan:?string,site:?string,jadwal:array<string,array{kode:string,masuk:?string,keluar:?string,menit:?int}>}>, total:int}
     */
    public function grid(DmsRosterMasterUpload $upload, int $halaman, int $perHalaman, string $cari = ''): array
    {
        $dasar = DB::table('dms_roster_master_jadwal')->where('upload_id', $upload->id);
        if ($cari !== '') {
            $mirip = '%'.addcslashes($cari, '%_\\').'%';
            $dasar->where(static fn ($q) => $q->where('kode_sid', 'like', $mirip)->orWhere('nama', 'like', $mirip));
        }

        $total = (clone $dasar)->distinct()->count('kode_sid');
        $sids = (clone $dasar)->select('kode_sid')->distinct()->orderBy('kode_sid')
            ->offset(max(0, $halaman - 1) * $perHalaman)->limit($perHalaman)->pluck('kode_sid')->all();

        $karyawan = [];
        foreach (DB::table('dms_roster_master_jadwal')->where('upload_id', $upload->id)->whereIn('kode_sid', $sids)
            ->orderBy('kode_sid')->orderBy('tanggal')->get() as $r) {
            $sid = (string) $r->kode_sid;
            $karyawan[$sid] ??= ['sid' => $sid, 'nama' => $r->nama, 'perusahaan' => $r->perusahaan, 'jabatan' => $r->jabatan, 'site' => $r->site, 'jadwal' => []];
            $karyawan[$sid]['jadwal'][CarbonImmutable::parse((string) $r->tanggal)->toDateString()] = [
                'kode' => (string) $r->kode,
                'masuk' => $r->jam_masuk === null ? null : substr((string) $r->jam_masuk, 0, 5),
                'keluar' => $r->jam_keluar === null ? null : substr((string) $r->jam_keluar, 0, 5),
                'menit' => $r->menit_kerja === null ? null : (int) $r->menit_kerja,
                // NULL / belum dimigrasi = sama dengan menit_kerja.
                'total' => isset($r->menit_total_kerja) ? (int) $r->menit_total_kerja : ($r->menit_kerja === null ? null : (int) $r->menit_kerja),
            ];
        }

        foreach ($karyawan as &$k) {
            [$k['jam_awal'], $k['jam_akhir']] = self::jamKaryawanDariJadwal($k['jadwal']);
            $k['jam_total'] = self::totalKaryawanDariJadwal($k['jadwal']);
        }
        unset($k);

        return ['karyawan' => array_values($karyawan), 'total' => $total];
    }

    /**
     * Isi kolom "Jam Kerja Awal / Akhir" per karyawan, dibalik dari jam efektif
     * per hari yang tersimpan: terisi hanya bila SEMUA hari kerjanya memakai
     * satu pasangan jam yang sama DAN berbeda dari jam bawaan kodenya (artinya
     * jam per karyawan diisi saat upload). Selain itu kosong, seperti template. Murni.
     *
     * @param  array<string, array{kode:string,masuk:?string,keluar:?string,menit:?int}>  $jadwal
     * @return array{0:?string,1:?string}
     */
    /** Label perusahaan untuk baris jadwal yang kolom perusahaannya kosong. */
    public const TANPA_PERUSAHAAN = '(tanpa perusahaan)';

    /**
     * Overview Roster: perusahaan yang sudah meng-upload roster (yang ditolak
     * tidak dihitung) beserta ringkasannya.
     *
     * @return list<array{perusahaan:string,karyawan:int,upload:int,disetujui:int,menunggu:int,dari:string,sampai:string,terakhir:?string,site:list<string>}>
     */
    public function perusahaanTerupload(): array
    {
        $pt = "COALESCE(NULLIF(TRIM(j.perusahaan), ''), '".self::TANPA_PERUSAHAAN."')";

        return DB::table('dms_roster_master_jadwal as j')
            ->join('dms_roster_master_upload as u', 'u.id', '=', 'j.upload_id')
            ->where('u.status', '<>', DmsRosterMasterUpload::STATUS_REJECTED)
            ->selectRaw("{$pt} AS perusahaan,
                COUNT(DISTINCT j.kode_sid) AS karyawan,
                COUNT(DISTINCT j.upload_id) AS upload,
                COUNT(DISTINCT CASE WHEN u.status = 'APPROVED' THEN j.upload_id END) AS disetujui,
                COUNT(DISTINCT CASE WHEN u.status = 'PENDING' THEN j.upload_id END) AS menunggu,
                MIN(j.tanggal) AS dari, MAX(j.tanggal) AS sampai, MAX(u.created_at) AS terakhir,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(j.site), '') ORDER BY j.site SEPARATOR '|') AS site")
            ->groupByRaw($pt)
            ->orderByRaw($pt)
            ->get()
            ->map(static fn (object $r): array => [
                'perusahaan' => (string) $r->perusahaan,
                'karyawan' => (int) $r->karyawan,
                'upload' => (int) $r->upload,
                'disetujui' => (int) $r->disetujui,
                'menunggu' => (int) $r->menunggu,
                'dari' => (string) $r->dari,
                'sampai' => (string) $r->sampai,
                'terakhir' => $r->terakhir === null ? null : (string) $r->terakhir,
                'site' => $r->site === null || $r->site === '' ? [] : explode('|', (string) $r->site),
            ])
            ->all();
    }

    /**
     * Jadwal BERLAKU satu perusahaan pada rentang tanggal, gabungan semua
     * roster: hanya yang disetujui (opsional + yang menunggu approval). Untuk
     * (SID, tanggal) yang ada di beberapa roster, roster disetujui yang paling
     * akhir menang (sama dengan aturan tumpuk di halaman approval); jadwal
     * dari roster yang masih menunggu ditandai 'menunggu' => true dan hanya
     * mengisi tanggal yang belum punya jadwal disetujui.
     *
     * @return array{karyawan: list<array<string, mixed>>, total: int}
     */
    public function jadwalBerlaku(
        string $perusahaan,
        CarbonImmutable $dari,
        CarbonImmutable $sampai,
        string $site = '',
        string $cari = '',
        bool $termasukMenunggu = false,
        int $halaman = 1,
        int $perHalaman = 25,
    ): array {
        $status = $termasukMenunggu
            ? [DmsRosterMasterUpload::STATUS_APPROVED, DmsRosterMasterUpload::STATUS_PENDING]
            : [DmsRosterMasterUpload::STATUS_APPROVED];

        $dasar = DB::table('dms_roster_master_jadwal as j')
            ->join('dms_roster_master_upload as u', 'u.id', '=', 'j.upload_id')
            ->whereIn('u.status', $status)
            ->whereBetween('j.tanggal', [$dari->toDateString(), $sampai->toDateString()]);
        if ($perusahaan === self::TANPA_PERUSAHAAN) {
            $dasar->where(static fn ($q) => $q->whereNull('j.perusahaan')->orWhereRaw("TRIM(j.perusahaan) = ''"));
        } else {
            $dasar->whereRaw('TRIM(j.perusahaan) = ?', [trim($perusahaan)]);
        }
        if ($site !== '') {
            $dasar->whereRaw('TRIM(j.site) = ?', [trim($site)]);
        }
        if ($cari !== '') {
            $mirip = '%'.addcslashes($cari, '%_\\').'%';
            $dasar->where(static fn ($q) => $q->where('j.kode_sid', 'like', $mirip)->orWhere('j.nama', 'like', $mirip));
        }

        $total = (clone $dasar)->distinct()->count('j.kode_sid');
        $sids = (clone $dasar)->groupBy('j.kode_sid')->selectRaw('j.kode_sid, MAX(j.nama) AS nm')
            ->orderBy('nm')->orderBy('j.kode_sid')
            ->offset(max(0, $halaman - 1) * $perHalaman)->limit($perHalaman)
            ->pluck('j.kode_sid')->all();
        if ($sids === []) {
            return ['karyawan' => [], 'total' => $total];
        }

        // Urutan naik prioritas: menunggu dulu, lalu disetujui; di dalamnya yang
        // lebih akhir belakangan — baris belakangan menimpa yang sebelumnya.
        $rows = (clone $dasar)->whereIn('j.kode_sid', $sids)
            ->select('j.*', 'u.status as status_upload')
            ->orderByRaw("CASE WHEN u.status = 'APPROVED' THEN 1 ELSE 0 END")
            ->orderByRaw('COALESCE(u.direview_pada, u.created_at)')
            ->orderBy('u.id')
            ->get();

        $karyawan = array_fill_keys($sids, null);
        foreach ($rows as $r) {
            $sid = (string) $r->kode_sid;
            $karyawan[$sid] ??= ['sid' => $sid, 'jadwal' => []];
            // Identitas dari baris paling akhir (prioritas tertinggi).
            $karyawan[$sid] = array_merge($karyawan[$sid], [
                'nama' => $r->nama, 'perusahaan' => $r->perusahaan, 'jabatan' => $r->jabatan, 'site' => $r->site,
            ]);
            $karyawan[$sid]['jadwal'][CarbonImmutable::parse((string) $r->tanggal)->toDateString()] = [
                'kode' => (string) $r->kode,
                'masuk' => $r->jam_masuk === null ? null : substr((string) $r->jam_masuk, 0, 5),
                'keluar' => $r->jam_keluar === null ? null : substr((string) $r->jam_keluar, 0, 5),
                'menit' => $r->menit_kerja === null ? null : (int) $r->menit_kerja,
                // NULL / belum dimigrasi = sama dengan menit_kerja.
                'total' => isset($r->menit_total_kerja) ? (int) $r->menit_total_kerja : ($r->menit_kerja === null ? null : (int) $r->menit_kerja),
                'menunggu' => $r->status_upload !== DmsRosterMasterUpload::STATUS_APPROVED,
            ];
        }

        $hasil = [];
        foreach ($karyawan as $k) {
            if ($k === null) {
                continue;
            }
            [$k['jam_awal'], $k['jam_akhir']] = self::jamKaryawanDariJadwal($k['jadwal']);
            $k['jam_total'] = self::totalKaryawanDariJadwal($k['jadwal']);
            $hasil[] = $k;
        }

        return ['karyawan' => $hasil, 'total' => $total];
    }

    /** Jam bawaan kode lama (upload sebelum D1–D13 / N1–N7) — hanya untuk membaca data lama. */
    private const JAM_KODE_LAMA = ['DS' => ['07:00', '19:00'], 'NS' => ['19:00', '07:00']];

    public static function jamKaryawanDariJadwal(array $jadwal): array
    {
        $pasangan = [];
        $adaBeda = false;
        foreach ($jadwal as $h) {
            if ($h['masuk'] === null) {
                continue;
            }
            $pasangan[$h['masuk'].'|'.$h['keluar']] = true;
            $bawaan = isset(self::KODE[$h['kode']]) ? [self::KODE[$h['kode']][2], self::KODE[$h['kode']][3]] : (self::JAM_KODE_LAMA[$h['kode']] ?? null);
            // Kode tak dikenal: jam bawaannya tidak diketahui, jadi tidak dianggap jam khusus.
            $adaBeda = $adaBeda || ($bawaan !== null && ($h['masuk'] !== $bawaan[0] || $h['keluar'] !== $bawaan[1]));
        }
        if (count($pasangan) !== 1 || ! $adaBeda) {
            return [null, null];
        }

        return explode('|', (string) array_key_first($pasangan));
    }

    /**
     * Template "ROSTER KERJA KARYAWAN" satu bulan, tata letak sama dengan
     * format yang diminta user: judul, legenda kode & jam, judul kolom tiga
     * baris (tanggal asli / hari / bulan), hari Minggu merah, dropdown kode,
     * dan warna sel otomatis per kode. Tanpa sel "Tanggal Awal Periode" dan
     * "Departemen / Site" (dihapus atas permintaan user) — pembaca tetap
     * menerima file lama yang masih memuatnya.
     */
    /**
     * @param  list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>  $karyawan
     *         Bila diisi (filter perusahaan), baris karyawan sudah terisi SID, nama,
     *         perusahaan, jabatan & site — sel jadwal dibiarkan kosong. Kosong = 3 baris contoh.
     */
    public function template(CarbonImmutable $dari, ?CarbonImmutable $sampai = null, array $karyawan = []): Spreadsheet
    {
        // Rentang bulan: awal bulan $dari s/d akhir bulan $sampai (maks 12 bulan).
        $awal = $dari->startOfMonth();
        $akhir = ($sampai ?? $dari)->endOfMonth()->startOfDay();
        if ($akhir->lt($awal)) {
            throw new \InvalidArgumentException('Bulan akhir harus sama dengan atau sesudah bulan awal.');
        }
        if ($awal->diffInMonths($akhir) >= self::MAKS_BULAN) {
            throw new \InvalidArgumentException('Rentang template maksimal '.self::MAKS_BULAN.' bulan.');
        }
        $jumlahHari = (int) $awal->diffInDays($akhir) + 1;
        $buku = new Spreadsheet();
        $sh = $buku->getActiveSheet()->setTitle('Roster');
        $buku->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $kolomTemplate = array_diff_key(self::KOLOM, array_flip(self::KOLOM_TANPA_TEMPLATE));
        $kolomAwalHari = count($kolomTemplate) + 1;           // 1-based (kolom I); kolom() 0-based
        $kolomAkhir = $this->kolom($kolomAwalHari + $jumlahHari - 2);
        $navy = '1F3864';
        $merah = 'C00000';

        // Kepala: judul + keterangan periode (informasi saja, tidak dibaca saat upload).
        $sh->setCellValue('A1', 'ROSTER KERJA KARYAWAN');
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB($navy);
        $sh->setCellValue('A2', 'Periode: '.$awal->format('d/m/Y').' s/d '.$akhir->format('d/m/Y')
            .' · ubah tanggal pertama (sel '.$this->kolom($kolomAwalHari - 1).'4) untuk bulan lain');
        $sh->mergeCells('A1:I1');
        $sh->mergeCells('A2:I2');
        $sh->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('595959');

        // Legenda kode di baris 2, mulai kolom hari pertama.
        $c = $kolomAwalHari;
        $sh->setCellValue($this->kolom($c - 1).'2', 'Kode:');
        $sh->getStyle($this->kolom($c - 1).'2')->getFont()->setBold(true);
        $sh->setCellValue($this->kolom($c - 1).'3', 'Jam:');
        $sh->getStyle($this->kolom($c - 1).'3')->getFont()->setBold(true);
        foreach (self::legenda() as [$kode, $ket, $warna, $masuk, $keluar]) {
            $sel = $this->kolom($c).'2';
            $sh->setCellValue($sel, $kode);
            $sh->getStyle($sel)->getFont()->setBold(true);
            $sh->getStyle($sel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle($sel)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($warna);
            $sh->getComment($sel)->getText()->createText($kode.' = '.$ket.($masuk ? ' ('.$masuk.'–'.$keluar.')' : ''));
            // Baris 3 di bawah kode: jam bawaannya.
            $jamSel = $this->kolom($c).'3';
            $sh->setCellValue($jamSel, $masuk ? $masuk.'–'.$keluar : '-');
            $sh->mergeCells($jamSel.':'.$this->kolom($c + 1).'3');
            $sh->getStyle($jamSel)->getFont()->setSize(8)->getColor()->setRGB('595959');
            $sh->getStyle($jamSel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            // Kotak "D1–D13" / "N1–N7" lebih lebar dari satu kolom hari.
            $lebar = str_contains($kode, '–') ? 3 : 2;
            if ($lebar === 3) {
                $sh->mergeCells($sel.':'.$this->kolom($c + 1).'2');
                $sh->unmergeCells($jamSel.':'.$this->kolom($c + 1).'3');
                $sh->mergeCells($jamSel.':'.$this->kolom($c + 2).'3');
            }
            $c += $lebar;
        }

        // Judul kolom identitas (digabung 3 baris).
        $c = 1;
        foreach ($kolomTemplate as $nama) {
            $kol = $this->kolom($c - 1);
            $sh->setCellValue($kol.'4', $nama);
            $sh->mergeCells($kol.'4:'.$kol.'6');
            $sh->getStyle($kol.'4')->getAlignment()->setWrapText(true);
            $c++;
        }
        $sh->getStyle('A4:'.$this->kolom(count($kolomTemplate) - 1).'6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Judul kolom hari. Tanpa "Tanggal Awal Periode", baris 4 berisi tanggal
        // Excel asli (tampil "dd"): sel pertama tanggal, sisanya =sebelumnya+1.
        // Nama hari & bulan dirumus dari tanggal itu (CHOOSE, bukan format "ddd"
        // supaya selalu bahasa Indonesia), dan Minggu merah lewat conditional
        // formatting — mengganti tanggal pertama memperbarui semuanya.
        $batasBulan = [];
        $hariRumus = '"'.implode('","', self::HARI).'"';
        $bulanRumus = '"'.implode('","', self::BULAN).'"';
        $kolPertama = $this->kolom($kolomAwalHari - 1);
        for ($i = 0; $i < $jumlahHari; $i++) {
            $kol = $this->kolom($kolomAwalHari + $i - 1);
            $sh->setCellValue($kol.'4', $i === 0
                ? ExcelDate::PHPToExcel($awal->toDateTime())
                : '='.$this->kolom($kolomAwalHari + $i - 2).'4+1');
            $sh->setCellValue($kol.'5', '=CHOOSE(WEEKDAY('.$kol.'4),'.$hariRumus.')');
            $sh->setCellValue($kol.'6', '=CHOOSE(MONTH('.$kol.'4),'.$bulanRumus.')');
            $sh->getColumnDimension($kol)->setWidth(5.2);
            if ($i > 0 && $awal->addDays($i)->day === 1) {
                $batasBulan[] = $kol;
            }
        }
        $judulHari = $kolPertama.'4:'.$kolomAkhir.'6';
        $sh->getStyle($kolPertama.'4:'.$kolomAkhir.'4')->getNumberFormat()->setFormatCode('dd');
        $sh->getStyle($kolPertama.'4:'.$kolomAkhir.'4')->getFont()->setBold(true)->getColor()->setRGB($merah);
        $sh->getStyle($kolPertama.'5:'.$kolomAkhir.'6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $navy]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
        ]);
        $minggu = new Conditional();
        $minggu->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('WEEKDAY('.$kolPertama.'$4)=1');
        $minggu->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
        $minggu->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB($merah);
        $sh->getStyle($judulHari)->setConditionalStyles([$minggu]);
        $sh->getStyle($judulHari)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Baris karyawan: dari filter perusahaan (jadwal kosong), atau 3 baris contoh.
        $contoh = $karyawan === [] ? [
            ['10023451', 'Ahmad Fauzi', 'PT Berau Coal', 'Operator HD', 'BMO 1', ['D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'OFF', 'N1', 'N2', 'N3', 'N4', 'N5', 'N6', 'N7', 'OFF']],
            ['10023452', 'Budi Santoso', 'Kontraktor A', 'Mekanik', 'BMO 1', ['N1', 'N2', 'N3', 'N4', 'N5', 'N6', 'N7', 'OFF', 'D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'OFF'], '05:30', '17:30', 11],
            ['10023453', 'Siti Rahmawati', 'PT Berau Coal', 'Safety Officer', 'HO', ['D1', 'D2', 'D3', 'D4', 'D5', 'OFF', 'OFF'], '07:30', '16:30', 8],
        ] : array_map(static fn (array $k): array => [$k['sid'], $k['nama'], $k['perusahaan'], $k['jabatan'], $k['site'], []], $karyawan);
        $barisAkhir = 6 + max($karyawan === [] ? 50 : 10, count($contoh) + ($karyawan === [] ? 0 : 10));
        foreach ($contoh as $i => $c0) {
            [$sid, $nama, $pt, $jab, $site, $pola] = $c0;
            $r = 7 + $i;
            $sh->setCellValue('A'.$r, $i + 1);
            $sh->setCellValueExplicit('B'.$r, $sid, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->fromArray([$nama, $pt, $jab, $site], null, 'C'.$r);
            if (isset($c0[6])) {
                $sh->setCellValue('G'.$r, $this->pecahanHari($c0[6]));
                $sh->setCellValue('H'.$r, $this->pecahanHari($c0[7]));
                // Contoh Total Jam Kerja yang diketik (dipotong istirahat).
                $sh->setCellValue('I'.$r, $c0[8]);
            }
            if ($pola !== []) {
                for ($h = 0; $h < $jumlahHari; $h++) {
                    $sh->setCellValue($this->kolom($kolomAwalHari + $h - 1).$r, $pola[$h % count($pola)]);
                }
            }
        }

        // Kolom Jam Kerja Awal / Akhir (G, H): format jam. Total Jam Kerja (I):
        // diketik manual (tanpa rumus) dalam jam, boleh lebih kecil dari
        // Akhir − Awal (dipotong istirahat); kosong = sama dengan Akhir − Awal.
        $sh->getStyle('G7:H'.$barisAkhir)->getNumberFormat()->setFormatCode('hh:mm');
        $sh->getStyle('G7:I'.$barisAkhir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('I7:I'.$barisAkhir)->getNumberFormat()->setFormatCode('0.##');
        $sh->getComment('I4')->getText()->createText('Ketik jam kerja efektif per hari (angka jam, mis. 11 atau 10,5). '
            .'Boleh lebih kecil dari Jam Kerja Akhir − Awal (dipotong istirahat), tidak boleh lebih besar. Kosong = Akhir − Awal.');

        // Area isian identitas: kuning muda, garis tipis.
        $sh->getStyle('A7:I'.$barisAkhir)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $sh->getStyle('B7:B'.$barisAkhir)->getNumberFormat()->setFormatCode('@');
        $sh->getStyle('A7:A'.$barisAkhir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('B7:B'.$barisAkhir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $abu = ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']];
        $sh->getStyle('A4:I'.$barisAkhir)->getBorders()->applyFromArray(['allBorders' => $abu]);
        $sh->getStyle($this->kolom($kolomAwalHari - 1).'4:'.$kolomAkhir.'6')->getBorders()->applyFromArray(['allBorders' => $abu]);

        // Area kode hari: gaya bawaan KOLOM (xf kolom), bukan gaya per sel —
        // perusahaan besar (PAMA ±3.700 orang) × 365 hari = 1,3 juta sel yang
        // tak perlu dibuat. (getStyle('J:J') juga tidak dipakai: itu memproses
        // sejuta baris.) Sel yang sudah berisi (baris contoh) diberi gaya sama.
        $areaKode = $this->kolom($kolomAwalHari - 1).'7:'.$kolomAkhir.$barisAkhir;
        $xfKolom = [];
        foreach ([false, true] as $awalBulan) {
            $gaya = new \PhpOffice\PhpSpreadsheet\Style\Style();
            $gaya->applyFromArray([
                'font' => ['bold' => true, 'name' => 'Calibri', 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['bottom' => $abu, 'right' => $abu]
                    + ($awalBulan ? ['left' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F3864']]] : []),
            ]);
            $buku->addCellXf($gaya);
            $xfKolom[(int) $awalBulan] = $gaya->getIndex();
        }
        for ($h = 0; $h < $jumlahHari; $h++) {
            $kol = $this->kolom($kolomAwalHari + $h - 1);
            $sh->getColumnDimension($kol)->setXfIndex($xfKolom[(int) in_array($kol, $batasBulan, true)]);
        }
        if ($karyawan === []) {
            $sh->getStyle($this->kolom($kolomAwalHari - 1).'7:'.$kolomAkhir.(6 + count($contoh)))->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => $abu],
            ]);
        }
        foreach ($batasBulan as $kol) {
            $sh->getStyle($kol.'4:'.$kol.'6')->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('1F3864');
        }

        // Warna otomatis per kode + dropdown kode.
        $kondisi = [];
        foreach (self::KODE as $kode => [, $warna]) {
            $k = new Conditional();
            $k->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition('"'.$kode.'"');
            $k->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB($warna);
            $kondisi[] = $k;
        }
        $sh->getStyle($areaKode)->setConditionalStyles($kondisi);

        $v = new DataValidation();
        $v->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
            ->setShowErrorMessage(true)->setErrorTitle('Kode tidak dikenal')
            ->setError('Pakai salah satu kode: '.implode(', ', array_keys(self::KODE)))
            ->setFormula1('"'.implode(',', array_keys(self::KODE)).'"');
        $sh->setDataValidation($areaKode, $v);

        foreach (['A' => 5, 'B' => 14, 'C' => 26, 'D' => 22, 'E' => 18, 'F' => 9, 'G' => 11, 'H' => 11, 'I' => 11] as $kol => $lebar) {
            $sh->getColumnDimension($kol)->setWidth($lebar);
        }
        $sh->freezePane($this->kolom($kolomAwalHari - 1).'7');

        $ket = $buku->createSheet()->setTitle('Petunjuk');
        $isi = [
            ['Petunjuk pengisian Roster Kerja Karyawan'],
            ['1. Baris tanggal (01, 02, …) berisi tanggal asli dan harus berurutan, maks '.self::MAKS_HARI.' hari (setahun). Mengganti tanggal pertama menggeser semua tanggal; jumlah kolom tetap sesuai rentang saat download.'],
            ['2. Satu baris = satu karyawan, mulai baris 7. Kolom SID wajib; No boleh kosong.'],
            ['3. Isi tiap hari dengan salah satu kode di bawah (pilih dari dropdown): D1–D13 = hari ke-1 s/d 13 blok Day, N1–N7 = hari ke-1 s/d 7 blok Night; sel kosong = tidak dijadwalkan.'],
            ['4. Jangan ubah judul kolom (No, SID, Nama Karyawan, Perusahaan, Jabatan, Site, Jam Kerja Awal, Jam Kerja Akhir, Total Jam Kerja). Hapus baris contoh sebelum upload.'],
            ['5. Jam Kerja Awal / Akhir (HH:MM) per karyawan boleh kosong = ikut jam bawaan kode ('
                .collect(self::legenda())->filter(fn (array $l): bool => $l[3] !== null)->map(fn (array $l): string => $l[0].' '.$l[3].'–'.$l[4])->implode(', ')
                .'). Bila diisi, berlaku untuk semua hari kerja (D1–D13 / N1–N7) orang itu;'],
            ['   jam akhir lebih kecil dari jam awal berarti lewat tengah malam (mis. 18:00–06:00 = 12 jam).'],
            ['   Total Jam Kerja diketik manual dalam jam (mis. 05:30–17:30 dengan istirahat 1 jam = 11). Tidak boleh lebih besar dari Akhir − Awal; kosong = sama dengan Akhir − Awal.'],
            ['6. File dengan satu kesalahan saja ditolak utuh beserta daftar kesalahannya.'],
            [''],
            ['Kode', 'Keterangan'],
        ];
        foreach (self::KODE as $kode => [$ket2]) {
            $isi[] = [$kode, $ket2];
        }
        $ket->fromArray($isi);
        $ket->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $ket->getStyle('A9:B9')->getFont()->setBold(true);
        $r = 10;
        foreach (self::KODE as [, $warna]) {
            $ket->getStyle('A'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($warna);
            $r++;
        }
        $ket->getColumnDimension('A')->setWidth(10);
        $ket->getColumnDimension('B')->setWidth(24);
        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    /**
     * Legenda kode untuk kepala template & halaman: kode berurutan yang
     * keterangan, warna, dan jamnya sama (D1–D13, N1–N7) diringkas satu entri.
     *
     * @return list<array{0:string,1:string,2:string,3:?string,4:?string}>  [kode, keterangan, warna, jam awal, jam akhir]
     */
    public static function legenda(): array
    {
        $out = [];
        foreach (self::KODE as $kode => [$ket, $warna, $masuk, $keluar]) {
            if (preg_match('/^([A-Z]+)(\d+)$/', $kode, $m) === 1) {
                $grup = $m[1];
                $terakhir = end($out);
                if ($terakhir !== false && ($terakhir['grup'] ?? null) === $grup && $terakhir[2] === $warna && $terakhir[3] === $masuk) {
                    $i = array_key_last($out);
                    $out[$i][0] = $terakhir['awal'].'–'.$kode;
                    $out[$i][1] = preg_replace('/\d+$/', '', $ket).$terakhir['n_awal'].'–'.$m[2];
                    continue;
                }
                $out[] = [$kode, $ket, $warna, $masuk, $keluar, 'grup' => $grup, 'awal' => $kode, 'n_awal' => $m[2]];

                continue;
            }
            $out[] = [$kode, $ket, $warna, $masuk, $keluar];
        }

        return array_map(static fn (array $l): array => [$l[0], $l[1], $l[2], $l[3], $l[4]], $out);
    }

    /**
     * Jam per karyawan dari kolom Jam Kerja Awal / Akhir / Total Jam Kerja.
     * Awal & Akhir kosong = ikut jam bawaan kode (null). Total Jam Kerja
     * (jam, desimal) boleh lebih kecil dari Akhir − Awal — mis. dipotong
     * istirahat — tapi tidak boleh lebih besar; kosong (atau rumus template
     * yang gagal dihitung) = sama dengan Akhir − Awal. Kesalahan ditambahkan ke $galat.
     *
     * @param  array<string, int>  $kolom
     * @param  list<string>  $galat
     * @return array{masuk:string,keluar:string,menit:int,total:int}|null
     */
    private function jamKaryawan(array $row, array $kolom, int $no, string $sid, array &$galat): ?array
    {
        $ambil = static fn (string $k) => isset($kolom[$k]) ? ($row[$kolom[$k]] ?? null) : null;
        $kosong = static fn ($v): bool => $v === null || trim((string) $v) === '';
        $mMasuk = $ambil('jam_masuk');
        $mKeluar = $ambil('jam_keluar');
        $mTotal = $ambil('total');
        // Rumus Total di template yang tidak bisa dihitung (#VALUE! dsb.) = kosong.
        if (is_string($mTotal) && str_starts_with(trim($mTotal), '#')) {
            $mTotal = null;
        }

        if ($kosong($mMasuk) && $kosong($mKeluar)) {
            if (! $kosong($mTotal)) {
                $galat[] = 'Baris '.$no.' ('.$sid.'): Total Jam Kerja diisi tanpa Jam Kerja Awal & Akhir.';
            }

            return null;
        }

        $masuk = $this->jam($mMasuk);
        $keluar = $this->jam($mKeluar);
        if ($masuk === null || $keluar === null) {
            $galat[] = 'Baris '.$no.' ('.$sid.'): Jam Kerja Awal dan Jam Kerja Akhir harus diisi berdua dengan format HH:MM'
                .' (sekarang "'.trim((string) $mMasuk).'" & "'.trim((string) $mKeluar).'").';

            return null;
        }

        $menit = $this->selisihMenit($masuk, $keluar);
        if ($menit === 0) {
            $galat[] = 'Baris '.$no.' ('.$sid.'): Jam Kerja Awal dan Akhir sama ('.$masuk.').';

            return null;
        }
        $totalMenit = $menit;
        if (! $kosong($mTotal)) {
            $total = str_replace(',', '.', trim((string) $mTotal));
            $totalMenit = is_numeric($total) ? (int) round((float) $total * 60) : null;
            if ($totalMenit === null || $totalMenit <= 0 || $totalMenit > $menit + 1) {
                $galat[] = 'Baris '.$no.' ('.$sid.'): Total Jam Kerja "'.trim((string) $mTotal).'" harus angka jam lebih dari 0 dan '
                    .'tidak lebih dari '.$masuk.'–'.$keluar.' = '.round($menit / 60, 2).' jam.';

                return null;
            }
            $totalMenit = min($totalMenit, $menit);
        }

        return ['masuk' => $masuk, 'keluar' => $keluar, 'menit' => $menit, 'total' => $totalMenit];
    }

    /**
     * Jam efektif satu hari: jam karyawan untuk hari kerja bila ada, kalau tidak
     * jam bawaan kodenya; hari bukan kerja tanpa jam.
     *
     * @param  array{masuk:string,keluar:string,menit:int,total:int}|null  $jamOrang
     * @return array{kode:string,masuk:?string,keluar:?string,menit:?int,total:?int}
     */
    private function jamHari(string $kode, ?array $jamOrang): array
    {
        [, , $masuk, $keluar] = self::KODE[$kode];
        if ($masuk === null) {
            return ['kode' => $kode, 'masuk' => null, 'keluar' => null, 'menit' => null, 'total' => null];
        }
        if ($jamOrang !== null) {
            return ['kode' => $kode] + $jamOrang;
        }
        $menit = $this->selisihMenit($masuk, $keluar);

        return ['kode' => $kode, 'masuk' => $masuk, 'keluar' => $keluar, 'menit' => $menit, 'total' => $menit];
    }

    /** "HH:MM", "H:MM", "HH.MM", atau pecahan hari Excel (0,2916…) → "HH:MM". */
    private function jam(mixed $nilai): ?string
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return null;
        }
        if (is_numeric($nilai) && (float) $nilai >= 0 && (float) $nilai < 1) {
            $m = (int) round((float) $nilai * 1440) % 1440;

            return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }
        if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', trim((string) $nilai), $x) && (int) $x[1] < 24 && (int) $x[2] < 60) {
            return sprintf('%02d:%02d', (int) $x[1], (int) $x[2]);
        }

        return null;
    }

    /** Menit dari $masuk ke $keluar; keluar <= masuk berarti lewat tengah malam. */
    private function selisihMenit(string $masuk, string $keluar): int
    {
        $m = static fn (string $j): int => (int) substr($j, 0, 2) * 60 + (int) substr($j, 3, 2);
        $d = $m($keluar) - $m($masuk);

        return $d < 0 ? $d + 1440 : $d;
    }

    private static ?bool $adaKolomTotal = null;

    /** Sudahkah kolom dms_roster_master_jadwal.menit_total_kerja dimigrasi. */
    public static function adaKolomTotal(): bool
    {
        if (self::$adaKolomTotal === null) {
            try {
                self::$adaKolomTotal = \Illuminate\Support\Facades\Schema::hasColumn('dms_roster_master_jadwal', 'menit_total_kerja');
            } catch (Throwable) {
                self::$adaKolomTotal = false;
            }
        }

        return self::$adaKolomTotal;
    }

    /**
     * Total jam kerja (jam, desimal) satu karyawan untuk tampilan: dari
     * menit_total_kerja hari kerjanya bila semuanya sama, kalau tidak null.
     *
     * @param  array<string, array<string, mixed>>  $jadwal
     */
    public static function totalKaryawanDariJadwal(array $jadwal): ?float
    {
        $nilai = [];
        foreach ($jadwal as $h) {
            if (($h['total'] ?? null) !== null) {
                $nilai[(int) $h['total']] = true;
            }
        }

        return count($nilai) === 1 ? round(array_key_first($nilai) / 60, 2) : null;
    }

    /** "HH:MM" → pecahan hari Excel. */
    private function pecahanHari(string $jam): float
    {
        return ((int) substr($jam, 0, 2) * 60 + (int) substr($jam, 3, 2)) / 1440;
    }

    /**
     * Sel tak kosong pertama di kanan kolom $c pada satu baris — berhenti di
     * label berikutnya (teks berakhiran ":", mis. "Kode:" / "Jam:" legenda, atau "Periode: …"),
     * supaya isian yang kosong tidak tertukar dengan label di kanannya.
     */
    private function selSesudah(array $row, int $c): mixed
    {
        for ($i = $c + 1; $i < count($row); $i++) {
            $t = trim((string) ($row[$i] ?? ''));
            if ($t === '') {
                continue;
            }

            // Teks keterangan "Periode: … s/d …" di template juga bukan isian.
            return str_ends_with($t, ':') || stripos($t, 'periode:') === 0 ? null : $row[$i];
        }

        return null;
    }

    /**
     * Tanggal satu judul kolom hari. Angka 1–31 = nomor hari: tanggalnya
     * $cursor bila harinya cocok, kalau tidak (lompat/salah urut) tetap
     * dikembalikan tanggal berhari itu supaya pemanggil melaporkan "tidak
     * berurutan"; null bila $cursor belum diketahui. Nomor seri Excel / teks
     * tanggal = tanggal itu sendiri. false = bukan judul tanggal.
     */
    private function tanggalKolom(mixed $sel, ?CarbonImmutable $cursor): string|false|null
    {
        $teks = trim((string) $sel);
        if (preg_match('/^\d{1,2}$/', $teks) === 1 && (int) $teks >= 1 && (int) $teks <= 31) {
            if ($cursor === null) {
                return null;
            }
            if ((int) $teks === $cursor->day) {
                return $cursor->toDateString();
            }
            // Hari tidak cocok: bentuk tanggal "seharusnya" di bulan cursor untuk pesan galat.
            return $cursor->day((int) min((int) $teks, $cursor->daysInMonth))->toDateString();
        }

        return $this->tanggal($sel) ?? false;
    }

    private function tanggal(mixed $nilai): ?string
    {
        if (is_numeric($nilai)) {
            // Nomor seri Excel yang masuk akal saja (>= 1 Jan 2000), bukan angka biasa.
            if ((float) $nilai < 36526) {
                return null;
            }
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $nilai))->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        // Carbon melempar (bukan false) bila format tidak cocok, jadi tiap format dicoba sendiri.
        $teks = trim((string) $nilai);
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                $t = CarbonImmutable::createFromFormat('!'.$format, $teks);
            } catch (Throwable) {
                continue;
            }
            if ($t !== false && $t->format($format) === $teks) {
                return $t->toDateString();
            }
        }

        return null;
    }

    private function kolom(int $indeks): string
    {
        return Coordinate::stringFromColumnIndex($indeks + 1);
    }
}
