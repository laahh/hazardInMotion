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
     * Jam null = bukan hari kerja. DS/NS mengikuti Shift 1/2 di docs/overshift.md;
     * OF (jam kantor, dibedakan dari OFF) 08:00–17:00 adalah asumsi — ubah di sini.
     */
    public const KODE = [
        'DS' => ['Day Shift', 'FFE699', '07:00', '19:00'],
        'NS' => ['Night Shift', '9DC3E6', '19:00', '07:00'],
        'OF' => ['Office (jam kantor)', 'C6E0B4', '08:00', '17:00'],
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
        'jam_masuk' => 'Jam Check-in',
        'jam_keluar' => 'Jam Check-out',
        'total' => 'Total Jam Kerja',
    ];

    private const KOLOM_OPSIONAL = ['no', 'jam_masuk', 'jam_keluar', 'total'];

    private const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const MAKS_HARI = 62;

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
                if ($teks === mb_strtolower($nama) && ! isset($kolom[$kunci])) {
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
                $galat[] = 'Tidak ada kolom tanggal sesudah kolom '.(isset($kolom['total']) ? 'Total Jam Kerja' : 'Site').'.';
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
            $rows = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
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

        $upload = DB::transaction(function () use ($file, $judul, $catatan, $oleh, $hasil, $jumlahJadwal): DmsRosterMasterUpload {
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
                    ];
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
    public function review(DmsRosterMasterUpload $upload, bool $setuju, ?string $catatan, string $oleh): void
    {
        if (! $upload->isPending()) {
            throw new \RuntimeException('Roster ini sudah direview ('.$upload->status.').');
        }

        $upload->update([
            'status' => $setuju ? DmsRosterMasterUpload::STATUS_APPROVED : DmsRosterMasterUpload::STATUS_REJECTED,
            'direview_oleh' => $oleh,
            'direview_pada' => now(),
            'catatan_review' => $catatan,
        ]);
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
            ];
        }

        return ['karyawan' => array_values($karyawan), 'total' => $total];
    }

    /**
     * Template "ROSTER KERJA KARYAWAN" satu bulan, tata letak sama dengan
     * format yang diminta user: kepala periode, legenda kode, judul tiga baris,
     * hari Minggu merah, dropdown kode, dan warna sel otomatis per kode.
     */
    public function template(CarbonImmutable $bulan): Spreadsheet
    {
        $awal = $bulan->startOfMonth();
        $jumlahHari = $awal->daysInMonth;
        $akhir = $awal->addDays($jumlahHari - 1);
        $buku = new Spreadsheet();
        $sh = $buku->getActiveSheet()->setTitle('Roster');
        $buku->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $kolomAwalHari = count(self::KOLOM) + 1;              // kolom ke-7 (G), 1-based; kolom() 0-based
        $kolomAkhir = $this->kolom($kolomAwalHari + $jumlahHari - 2);
        $navy = '1F3864';
        $merah = 'C00000';

        // Kepala.
        $sh->setCellValue('A1', 'ROSTER KERJA KARYAWAN');
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB($navy);
        $sh->setCellValue('A2', 'Tanggal Awal Periode:');
        $sh->setCellValue('D2', ExcelDate::PHPToExcel($awal->toDateTime()));
        $sh->getStyle('D2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sh->setCellValue('E2', 'Periode: '.$awal->format('d/m/Y').' s/d '.$akhir->format('d/m/Y'));
        $sh->setCellValue('A3', 'Departemen / Site:');
        $sh->mergeCells('A1:I1');
        $sh->mergeCells('E2:I2');
        $sh->mergeCells('A2:C2');
        $sh->mergeCells('A3:C3');
        $sh->mergeCells('D3:I3');
        $sh->getStyle('A2:A3')->getFont()->setBold(true);
        $sh->getStyle('D2')->getFont()->setBold(true);
        $sh->getStyle('D2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sh->getStyle('E2')->getFont()->setItalic(true)->getColor()->setRGB('595959');
        $sh->getStyle('D2:D3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $sh->getStyle('D3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');

        // Legenda kode di baris 2, mulai kolom hari pertama.
        $c = $kolomAwalHari;
        $sh->setCellValue($this->kolom($c - 1).'2', 'Kode:');
        $sh->getStyle($this->kolom($c - 1).'2')->getFont()->setBold(true);
        $sh->setCellValue($this->kolom($c - 1).'3', 'Jam:');
        $sh->getStyle($this->kolom($c - 1).'3')->getFont()->setBold(true);
        foreach (self::KODE as $kode => [$ket, $warna, $masuk, $keluar]) {
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
            $c += 2;
        }

        // Judul kolom identitas (digabung 3 baris).
        $c = 1;
        foreach (self::KOLOM as $nama) {
            $kol = $this->kolom($c - 1);
            $sh->setCellValue($kol.'4', $nama);
            $sh->mergeCells($kol.'4:'.$kol.'6');
            $sh->getStyle($kol.'4')->getAlignment()->setWrapText(true);
            $c++;
        }
        $sh->getStyle('A4:'.$this->kolom(count(self::KOLOM) - 1).'6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Judul kolom hari: tanggal / nama hari / bulan; Minggu merah.
        for ($i = 0; $i < $jumlahHari; $i++) {
            $t = $awal->addDays($i);
            $kol = $this->kolom($kolomAwalHari + $i - 1);
            $sh->setCellValueExplicit($kol.'4', $t->format('d'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->setCellValue($kol.'5', self::HARI[$t->dayOfWeek]);
            $sh->setCellValue($kol.'6', self::BULAN[$t->month]);
            $minggu = $t->dayOfWeek === 0;
            $sh->getStyle($kol.'4')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $minggu ? 'FFFFFF' : $merah]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $minggu ? $merah : 'FFFFFF']],
            ]);
            $sh->getStyle($kol.'5:'.$kol.'6')->getFont()->setBold(true)->getColor()->setRGB($minggu ? 'FFFFFF' : $navy);
            $sh->getStyle($kol.'5:'.$kol.'6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($minggu ? $merah : 'D9E1F2');
            $sh->getColumnDimension($kol)->setWidth(5.2);
        }
        $sh->getStyle($this->kolom($kolomAwalHari - 1).'4:'.$kolomAkhir.'6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Baris contoh.
        $contoh = [
            ['10023451', 'Ahmad Fauzi', 'PT Berau Coal', 'Operator HD', 'BMO 1', ['DS', 'DS', 'DS', 'DS', 'DS', 'DS', 'DS', 'OFF', 'NS', 'NS', 'NS', 'NS', 'NS', 'NS', 'OFF']],
            ['10023452', 'Budi Santoso', 'Kontraktor A', 'Mekanik', 'BMO 1', ['NS', 'NS', 'NS', 'NS', 'NS', 'NS', 'NS', 'OFF', 'DS', 'DS', 'DS', 'DS', 'DS', 'DS', 'OFF'], '06:00', '18:00'],
            ['10023453', 'Siti Rahmawati', 'PT Berau Coal', 'Safety Officer', 'HO', ['OF', 'OF', 'OF', 'OF', 'OF', 'OFF', 'OFF'], '07:30', '16:30'],
        ];
        $barisAkhir = 6 + max(50, count($contoh));
        foreach ($contoh as $i => $c0) {
            [$sid, $nama, $pt, $jab, $site, $pola] = $c0;
            $r = 7 + $i;
            $sh->setCellValue('A'.$r, $i + 1);
            $sh->setCellValueExplicit('B'.$r, $sid, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->fromArray([$nama, $pt, $jab, $site], null, 'C'.$r);
            if (isset($c0[6])) {
                $sh->setCellValue('G'.$r, $this->pecahanHari($c0[6]));
                $sh->setCellValue('H'.$r, $this->pecahanHari($c0[7]));
            }
            for ($h = 0; $h < $jumlahHari; $h++) {
                $sh->setCellValue($this->kolom($kolomAwalHari + $h - 1).$r, $pola[$h % count($pola)]);
            }
        }

        // Kolom jam: format jam, Total Jam Kerja = rumus (lintas tengah malam via MOD).
        for ($r = 7; $r <= $barisAkhir; $r++) {
            $sh->setCellValue('I'.$r, '=IF(AND(G'.$r.'<>"",H'.$r.'<>""),ROUND(MOD(H'.$r.'-G'.$r.',1)*24,2),"")');
        }
        $sh->getStyle('G7:H'.$barisAkhir)->getNumberFormat()->setFormatCode('hh:mm');
        $sh->getStyle('I7:I'.$barisAkhir)->getNumberFormat()->setFormatCode('0.##');
        $sh->getStyle('G7:I'.$barisAkhir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('I7:I'.$barisAkhir)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDEDED');

        // Area isian: identitas kuning muda, kode di tengah, garis tipis.
        $sh->getStyle('A7:H'.$barisAkhir)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $sh->getStyle('B7:B'.$barisAkhir)->getNumberFormat()->setFormatCode('@');
        $sh->getStyle('A7:A'.$barisAkhir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('B7:B'.$barisAkhir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $areaKode = $this->kolom($kolomAwalHari - 1).'7:'.$kolomAkhir.$barisAkhir;
        $sh->getStyle($areaKode)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle($areaKode)->getFont()->setBold(true);
        $sh->getStyle('A4:'.$kolomAkhir.$barisAkhir)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');

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
            ['1. Isi "Tanggal Awal Periode" (DD/MM/YYYY). Kolom tanggal harus berurutan mulai tanggal itu, maks '.self::MAKS_HARI.' hari.'],
            ['2. Satu baris = satu karyawan, mulai baris 7. Kolom SID wajib; No boleh kosong.'],
            ['3. Isi tiap hari dengan salah satu kode di bawah; sel kosong = tidak dijadwalkan.'],
            ['4. Jangan ubah judul kolom (No, SID, Nama Karyawan, Perusahaan, Jabatan, Site, Jam Check-in, Jam Check-out, Total Jam Kerja). Hapus baris contoh sebelum upload.'],
            ['5. Jam Check-in / Check-out (HH:MM) per karyawan boleh kosong = ikut jam bawaan kode di tabel bawah. Bila diisi, berlaku untuk semua hari kerja (DS/NS/OF) orang itu.'],
            ['   Total Jam Kerja terhitung otomatis dari jam check-in & check-out (lintas tengah malam diperhitungkan); bila diisi manual harus sama.'],
            ['6. File dengan satu kesalahan saja ditolak utuh beserta daftar kesalahannya.'],
            [''],
            ['Kode', 'Keterangan', 'Jam Check-in', 'Jam Check-out', 'Total Jam Kerja'],
        ];
        foreach (self::KODE as $kode => [$ket2, , $masuk, $keluar]) {
            $isi[] = [$kode, $ket2, $masuk ?? '-', $keluar ?? '-', $masuk ? round($this->selisihMenit($masuk, $keluar) / 60, 2) : 0];
        }
        $ket->fromArray($isi);
        $ket->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $ket->getStyle('A9:E9')->getFont()->setBold(true);
        $r = 10;
        foreach (self::KODE as [, $warna]) {
            $ket->getStyle('A'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($warna);
            $r++;
        }
        $ket->getColumnDimension('A')->setWidth(10);
        $ket->getColumnDimension('B')->setWidth(24);
        foreach (['C', 'D', 'E'] as $kol) {
            $ket->getColumnDimension($kol)->setWidth(15);
        }
        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    /**
     * Jam per karyawan dari kolom Jam Check-in / Check-out / Total Jam Kerja.
     * Keduanya kosong = ikut jam bawaan kode (null). Kesalahan ditambahkan ke $galat.
     *
     * @param  array<string, int>  $kolom
     * @param  list<string>  $galat
     * @return array{masuk:string,keluar:string,menit:int}|null
     */
    private function jamKaryawan(array $row, array $kolom, int $no, string $sid, array &$galat): ?array
    {
        $ambil = static fn (string $k) => isset($kolom[$k]) ? ($row[$kolom[$k]] ?? null) : null;
        $kosong = static fn ($v): bool => $v === null || trim((string) $v) === '';
        $mMasuk = $ambil('jam_masuk');
        $mKeluar = $ambil('jam_keluar');
        $mTotal = $ambil('total');

        if ($kosong($mMasuk) && $kosong($mKeluar)) {
            if (! $kosong($mTotal)) {
                $galat[] = 'Baris '.$no.' ('.$sid.'): Total Jam Kerja diisi tanpa Jam Check-in & Check-out.';
            }

            return null;
        }

        $masuk = $this->jam($mMasuk);
        $keluar = $this->jam($mKeluar);
        if ($masuk === null || $keluar === null) {
            $galat[] = 'Baris '.$no.' ('.$sid.'): Jam Check-in dan Jam Check-out harus diisi berdua dengan format HH:MM'
                .' (sekarang "'.trim((string) $mMasuk).'" & "'.trim((string) $mKeluar).'").';

            return null;
        }

        $menit = $this->selisihMenit($masuk, $keluar);
        if ($menit === 0) {
            $galat[] = 'Baris '.$no.' ('.$sid.'): Jam Check-in dan Check-out sama ('.$masuk.').';

            return null;
        }
        if (! $kosong($mTotal)) {
            $total = str_replace(',', '.', trim((string) $mTotal));
            if (! is_numeric($total) || abs((float) $total * 60 - $menit) > 1) {
                $galat[] = 'Baris '.$no.' ('.$sid.'): Total Jam Kerja "'.trim((string) $mTotal).'" tidak sama dengan '
                    .$masuk.'–'.$keluar.' = '.round($menit / 60, 2).' jam.';
            }
        }

        return ['masuk' => $masuk, 'keluar' => $keluar, 'menit' => $menit];
    }

    /**
     * Jam efektif satu hari: jam karyawan untuk hari kerja bila ada, kalau tidak
     * jam bawaan kodenya; hari bukan kerja tanpa jam.
     *
     * @param  array{masuk:string,keluar:string,menit:int}|null  $jamOrang
     * @return array{kode:string,masuk:?string,keluar:?string,menit:?int}
     */
    private function jamHari(string $kode, ?array $jamOrang): array
    {
        [, , $masuk, $keluar] = self::KODE[$kode];
        if ($masuk === null) {
            return ['kode' => $kode, 'masuk' => null, 'keluar' => null, 'menit' => null];
        }
        if ($jamOrang !== null) {
            return ['kode' => $kode] + $jamOrang;
        }

        return ['kode' => $kode, 'masuk' => $masuk, 'keluar' => $keluar, 'menit' => $this->selisihMenit($masuk, $keluar)];
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
