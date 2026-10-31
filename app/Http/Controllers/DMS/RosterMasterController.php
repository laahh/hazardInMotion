<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\DmsRosterMasterUpload;
use App\Models\User;
use App\Services\Dms\Roster\DmsRosterAksesPerusahaan;
use App\Services\Dms\Roster\DmsRosterCakupanReader;
use App\Services\Dms\Roster\DmsRosterKaryawanAktif;
use App\Services\Dms\Roster\DmsRosterMasterEditService;
use App\Services\Dms\Roster\DmsRosterMasterService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /dms/master-roster — Master Roster: upload jadwal kerja per karyawan
 * (Excel) dan approval-nya oleh admin. Logika baca & simpan file di
 * DmsRosterMasterService.
 */
final class RosterMasterController extends Controller
{
    private const PER_HALAMAN = 20;

    public function __construct(
        private readonly DmsRosterMasterService $service,
        private readonly DmsRosterKaryawanAktif $karyawanAktif,
        private readonly DmsRosterAksesPerusahaan $akses,
        private readonly DmsRosterCakupanReader $cakupan,
    ) {}

    /**
     * Halaman Upload Roster: form upload + riwayat upload.
     */
    public function upload(Request $request): View
    {
        return view('dms.roster-master.upload', [
            'daftar' => $this->daftar($request, ''),
            'ringkas' => $this->ringkas(),
            'bulanTemplate' => CarbonImmutable::now()->addMonthNoOverflow()->format('Y-m'),
            'tahunTemplate' => CarbonImmutable::now()->addMonthsNoOverflow(1)->year,
            // Daftar perusahaan langsung dari bep_vw_safety_karyawan_aktif (cache 1 jam);
            // ?segarkan_pt=1 memaksa ambil ulang. null = view tidak terjangkau & belum ada salinan.
            'perusahaan' => $this->karyawanAktif->perusahaan($request->boolean('segarkan_pt')),
            'perusahaanGalat' => $this->karyawanAktif->galatTerakhir(),
        ]);
    }

    /**
     * Halaman Approval Roster: daftar upload, bawaan yang menunggu approval.
     */
    /**
     * Overview Roster: daftar semua perusahaan yang sudah meng-upload roster.
     * Jadwal tiap perusahaan dibuka per baris (collapse, seperti tabel
     * Scorecard Ringkasan Roster) dan dimuat dari overviewJadwal() saat dibuka.
     * ?pt= membuka perusahaan itu sejak awal.
     */
    public function overview(Request $request): View
    {
        $perusahaan = $this->service->perusahaanTerupload($this->cakupan->peta());

        return view('dms.roster-master.overview', [
            'perusahaan' => $this->statusPeriode($perusahaan),
            'ringkas' => $this->ringkasOverview($perusahaan),
            'buka' => (string) $request->query('pt', ''),
            'cakupan' => $this->cakupan->cakupan(),
        ]);
    }

    /** Batas "segera habis" di Overview Roster: periode roster tinggal sekian hari. */
    private const SEGERA_HABIS_HARI = 7;

    /**
     * Tambahkan status periode terhadap hari ini + posisi bar garis waktu
     * (persen dari rentang gabungan semua perusahaan).
     *
     * @param  list<array<string, mixed>>  $perusahaan
     * @return list<array<string, mixed>>
     */
    private function statusPeriode(array $perusahaan): array
    {
        if ($perusahaan === []) {
            return [];
        }
        $hariIni = CarbonImmutable::today();
        $awal = CarbonImmutable::parse(min(array_column($perusahaan, 'dari')));
        $akhir = CarbonImmutable::parse(max(array_column($perusahaan, 'sampai')));
        $total = max(1, $awal->diffInDays($akhir) + 1);
        $maksKaryawan = max(1, ...array_column($perusahaan, 'karyawan'));

        return array_map(function (array $p) use ($hariIni, $awal, $total, $maksKaryawan): array {
            $dari = CarbonImmutable::parse($p['dari']);
            $sampai = CarbonImmutable::parse($p['sampai']);
            $sisa = (int) $hariIni->diffInDays($sampai, false);

            [$status, $label] = match (true) {
                $sampai->lt($hariIni) => ['berakhir', 'Berakhir '.$sampai->format('d/m')],
                $dari->gt($hariIni) => ['belum', 'Mulai '.$dari->format('d/m')],
                $sisa < self::SEGERA_HABIS_HARI => ['segera', $sisa === 0 ? 'Habis hari ini' : 'Habis '.$sisa.' hari lagi'],
                default => ['berlaku', 'Berlaku · sisa '.$sisa.' hari'],
            };

            return $p + [
                'status' => $status,
                'status_label' => $label,
                'bar_kiri' => round($awal->diffInDays($dari) / $total * 100, 2),
                'bar_lebar' => max(1.5, round(($dari->diffInDays($sampai) + 1) / $total * 100, 2)),
                'karyawan_persen' => round($p['karyawan'] / $maksKaryawan * 100, 1),
            ];
        }, $perusahaan);
    }

    /**
     * Angka KPI Overview Roster.
     *
     * @param  list<array<string, mixed>>  $perusahaan
     * @return array<string, mixed>
     */
    private function ringkasOverview(array $perusahaan): array
    {
        $hariIni = CarbonImmutable::today();
        $awal = $perusahaan === [] ? $hariIni : CarbonImmutable::parse(min(array_column($perusahaan, 'dari')));
        $akhir = $perusahaan === [] ? $hariIni : CarbonImmutable::parse(max(array_column($perusahaan, 'sampai')));
        $total = max(1, $awal->diffInDays($akhir) + 1);
        $berlaku = array_filter($perusahaan, static fn (array $p): bool => $p['dari'] <= $hariIni->toDateString() && $p['sampai'] >= $hariIni->toDateString());
        $segera = array_filter($berlaku, fn (array $p): bool => (int) $hariIni->diffInDays(CarbonImmutable::parse($p['sampai']), false) < self::SEGERA_HABIS_HARI);

        return [
            'perusahaan' => count($perusahaan),
            'berlaku' => count($berlaku),
            'karyawan' => array_sum(array_column($perusahaan, 'karyawan')),
            'karyawan_berlaku' => array_sum(array_column($berlaku, 'karyawan')),
            'disetujui' => array_sum(array_column($perusahaan, 'disetujui')),
            'menunggu' => array_sum(array_column($perusahaan, 'menunggu')),
            'pt_menunggu' => array_values(array_column(array_filter($perusahaan, static fn (array $p): bool => $p['menunggu'] > 0), 'perusahaan')),
            'pt_segera' => array_values(array_column($segera, 'perusahaan')),
            'segera_hari' => self::SEGERA_HABIS_HARI,
            'rentang_awal' => $awal,
            'rentang_akhir' => $akhir,
            'hari_ini_persen' => $hariIni->between($awal, $akhir) ? round($awal->diffInDays($hariIni) / $total * 100, 2) : null,
        ];
    }

    /**
     * Isi panel jadwal satu perusahaan (fragmen HTML) — tabel bergaya Excel
     * per bulan dengan filter site, cari, dan "termasuk menunggu approval".
     */
    public function overviewJadwal(Request $request): View
    {
        $terpilih = collect($this->service->perusahaanTerupload($this->cakupan->peta()))->firstWhere('perusahaan', (string) $request->query('pt', ''));
        abort_if($terpilih === null, 404);

        $halaman = max(1, (int) $request->query('hal', 1));
        $site = mb_substr(trim((string) $request->query('site', '')), 0, 60);
        $cari = mb_substr(trim((string) $request->query('q', '')), 0, 50);
        $menunggu = $request->boolean('menunggu');
        if (! in_array($site, $terpilih['site'], true)) {
            $site = '';
        }

        // Bulan bawaan: bulan ini bila masuk rentang roster perusahaan, kalau tidak bulan terakhirnya.
        $dariData = CarbonImmutable::parse($terpilih['dari'])->startOfMonth();
        $sampaiData = CarbonImmutable::parse($terpilih['sampai'])->startOfMonth();
        $minta = (string) $request->query('bulan', '');
        $bulan = preg_match('/^\d{4}-\d{2}$/', $minta) === 1 ? CarbonImmutable::createFromFormat('!Y-m', $minta) : null;
        if ($bulan === null) {
            $kini = CarbonImmutable::now()->startOfMonth();
            $bulan = $kini->betweenIncluded($dariData, $sampaiData) ? $kini : $sampaiData;
        }

        $tanggal = [];
        for ($t = $bulan->startOfMonth(); $t->lte($bulan->endOfMonth()); $t = $t->addDay()) {
            $tanggal[] = $t;
        }
        $grid = $this->service->jadwalBerlaku($terpilih['perusahaan'], $bulan->startOfMonth(), $bulan->endOfMonth(),
            $site, $cari, $menunggu, $halaman, self::PER_HALAMAN_GRID, $terpilih['ejaan']);

        return view('dms.roster-master._overview-jadwal', [
            'terpilih' => $terpilih,
            'bulan' => $bulan,
            'site' => $site,
            'cari' => $cari,
            'menunggu' => $menunggu,
            'grid' => $grid,
            'tanggal' => $tanggal,
            'halaman' => $halaman,
            'perHalaman' => self::PER_HALAMAN_GRID,
            'totalHalaman' => max(1, (int) ceil($grid['total'] / self::PER_HALAMAN_GRID)),
        ]);
    }

    private const PER_HALAMAN_GRID = 25;

    /**
     * Isi modal "Kurang" di kartu Cakupan upload roster (fragmen HTML): untuk
     * satu perusahaan aktif, karyawan aktif yang BELUM ada di roster mana pun
     * (sel jadwal kosong), atau yang SUDAH — dengan jadwal gabungan per bulan
     * (dobel = upload terbaru, aturan sama dengan Edit Roster).
     */
    public function overviewKurang(Request $request, DmsRosterMasterEditService $gabungan): View
    {
        $pt = mb_substr(trim((string) $request->query('pt', '')), 0, 255);
        abort_if($pt === '', 404);
        // Filter site di kartu cakupan ikut dibawa ke modal ('' = semua site).
        $site = mb_substr(trim((string) $request->query('site', '')), 0, 60);
        $tampil = $request->query('tampil') === 'sudah' ? 'sudah' : 'belum';
        $halaman = max(1, (int) $request->query('hal', 1));
        $cari = mb_substr(trim((string) $request->query('q', '')), 0, 50);
        $minta = (string) $request->query('bulan', '');
        $bulan = preg_match('/^\d{4}-\d{2}$/', $minta) === 1 ? CarbonImmutable::createFromFormat('!Y-m', $minta) : CarbonImmutable::now()->startOfMonth();
        $tanggal = [];
        for ($t = $bulan->startOfMonth(); $t->lte($bulan->endOfMonth()); $t = $t->addDay()) {
            $tanggal[] = $t;
        }

        $aktif = $this->karyawanAktif->untukPerusahaan($pt, $site);
        $ejaan = $this->cakupan->ejaanUntuk($pt);
        $diRoster = $this->cakupan->sidRoster($ejaan);
        $mirip = static fn (array $k): bool => $cari === '' || stripos($k['sid'].' '.$k['nama'], $cari) !== false;

        $belum = $aktif === null ? [] : array_values(array_filter($aktif, static fn (array $k): bool => ! isset($diRoster[mb_strtoupper($k['sid'])])));
        if ($tampil === 'belum') {
            $daftar = array_values(array_filter($belum, $mirip));
            $grid = [
                'total' => count($daftar),
                'karyawan' => array_map(static fn (array $k): array => $k + ['jam_awal' => null, 'jam_akhir' => null, 'jam_total' => null, 'jadwal' => []],
                    array_slice($daftar, ($halaman - 1) * self::PER_HALAMAN_GRID, self::PER_HALAMAN_GRID)),
            ];
        } else {
            $grid = $gabungan->grid($ejaan, $bulan->startOfMonth(), $bulan->endOfMonth(), $cari, $halaman, self::PER_HALAMAN_GRID, $site);
        }

        return view('dms.roster-master._kurang', [
            'pt' => $pt,
            'site' => $site,
            'tampil' => $tampil,
            'bulan' => $bulan,
            'tanggal' => $tanggal,
            'cari' => $cari,
            'grid' => $grid,
            'halaman' => $halaman,
            'perHalaman' => self::PER_HALAMAN_GRID,
            'totalHalaman' => max(1, (int) ceil($grid['total'] / self::PER_HALAMAN_GRID)),
            'aktifTerbaca' => $aktif !== null,
            'jumlah' => ['aktif' => $aktif === null ? null : count($aktif), 'belum' => count($belum), 'roster' => count($diRoster)],
        ]);
    }

    public function approval(Request $request): View
    {
        $status = (string) $request->query('status', DmsRosterMasterUpload::STATUS_PENDING);
        // Assign Role: hanya roster perusahaan yang di-assign ke user ini.
        $cakupan = $this->cakupan();

        return view('dms.roster-master.approval', [
            'daftar' => $this->daftar($request, $status, $cakupan, true),
            'ringkas' => $this->ringkas($cakupan, true),
            'status' => $status,
            'cakupan' => $cakupan,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'judul.required' => 'Judul roster wajib diisi.',
            'file.required' => 'File Excel roster wajib diupload.',
            'file.mimes' => 'File harus Excel (.xlsx / .xls).',
        ]);

        $hasil = $this->service->simpan($request->file('file'), $data['judul'], $data['catatan'] ?? null, $this->pengguna());

        if ($hasil['upload'] === null) {
            return back()->withInput()->with('galat_roster', $hasil['galat']);
        }

        $u = $hasil['upload'];

        return redirect()
            ->route('dms.master-roster.show', $u->id)
            ->with('success', 'Roster "'.$u->judul.'" terupload: '.number_format($u->jumlah_karyawan, 0, ',', '.').' karyawan, '
                .number_format($u->jumlah_jadwal, 0, ',', '.').' jadwal. Menunggu approval.');
    }

    public function show(Request $request, int $id): View
    {
        $upload = DmsRosterMasterUpload::findOrFail($id);
        $cakupan = $this->cakupan();
        $ptUpload = $this->akses->perusahaanUpload($upload->id);
        abort_unless(DmsRosterAksesPerusahaan::terlihat($cakupan, $ptUpload), 403, 'Roster ini bukan perusahaan yang di-assign ke akun Anda.');
        $halaman = max(1, (int) $request->query('hal', 1));
        $cari = mb_substr(trim((string) $request->query('q', '')), 0, 50);
        $grid = $this->service->grid($upload, $halaman, 25, $cari);

        $tanggal = [];
        for ($t = CarbonImmutable::parse($upload->periode_mulai); $t->lte($upload->periode_selesai); $t = $t->addDay()) {
            $tanggal[] = $t;
        }

        return view('dms.roster-master.show', [
            'upload' => $upload,
            'grid' => $grid,
            'tanggal' => $tanggal,
            'halaman' => $halaman,
            'totalHalaman' => max(1, (int) ceil($grid['total'] / 25)),
            'cari' => $cari,
            'tumpang' => $upload->isPending() ? $this->service->jumlahTumpang($upload) : 0,
            'ptUpload' => $ptUpload,
            'bolehReview' => DmsRosterAksesPerusahaan::mencakup($cakupan, $ptUpload),
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        return $this->review($request, $id, true);
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        return $this->review($request, $id, false);
    }

    public function destroy(int $id): RedirectResponse
    {
        $upload = DmsRosterMasterUpload::findOrFail($id);
        $user = Auth::user();
        abort_unless($upload->bolehDihapusOleh($user instanceof \App\Models\User ? $user : null), 403,
            'Hanya pengupload roster ini atau admin yang boleh menghapusnya, dan hanya selama belum disetujui.');

        $jadwal = (int) $upload->jumlah_jadwal;
        try {
            $this->service->hapus($upload);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dms.master-roster.upload')->with('success', 'Roster "'.$upload->judul.'" dihapus beserta '
            .number_format($jadwal, 0, ',', '.').' jadwalnya.');
    }

    /**
     * Template untuk rentang bulan ?dari=YYYY-MM&sampai=YYYY-MM (maks 12 bulan,
     * mis. Januari–Desember). ?bulan=YYYY-MM lama tetap diterima (satu bulan).
     * ?perusahaan=<nama> mengisi baris karyawan perusahaan itu (SID, nama,
     * jabatan, site), ?site=<site> membatasi ke satu site; jadwalnya tinggal diisi.
     */
    public function template(Request $request): StreamedResponse|RedirectResponse
    {
        $bulan = static fn (string $v): ?CarbonImmutable => preg_match('/^\d{4}-\d{2}$/', $v) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $v)
            : null;
        $dari = $bulan((string) $request->query('dari', $request->query('bulan', '')))
            ?? CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
        $sampai = $bulan((string) $request->query('sampai', '')) ?? $dari;

        // Filter perusahaan: baris karyawan terisi dari bep_vw_safety_karyawan_aktif.
        $pt = mb_substr(trim((string) $request->query('perusahaan', '')), 0, 255);
        $site = mb_substr(trim((string) $request->query('site', '')), 0, 60);
        $karyawan = [];
        if ($pt !== '') {
            $karyawan = $this->karyawanAktif->untukPerusahaan($pt, $site);
            if ($karyawan === null) {
                return redirect()->route('dms.master-roster.upload')
                    ->with('error', 'Daftar karyawan aktif belum tersedia, coba lagi setelah sinkronisasi berikutnya.');
            }
            if ($karyawan === []) {
                return redirect()->route('dms.master-roster.upload')
                    ->with('error', 'Tidak ada karyawan aktif untuk perusahaan "'.$pt.'"'.($site !== '' ? ' di site "'.$site.'"' : '').'.');
            }
        }

        try {
            $writer = new Xlsx($this->service->template($dari, $sampai, $karyawan));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('dms.master-roster.upload')->with('error', 'Template tidak dibuat: '.$e->getMessage());
        }

        $nama = 'template_master_roster_'
            .($pt !== '' ? \Illuminate\Support\Str::slug(\App\Services\Dms\Roster\DmsRosterSyncService::kodePt($pt)).'_' : '')
            .($pt !== '' && $site !== '' ? \Illuminate\Support\Str::slug($site).'_' : '')
            .$dari->format('Y-m').($sampai->format('Y-m') !== $dari->format('Y-m') ? '_sd_'.$sampai->format('Y-m') : '').'.xlsx';

        return response()->streamDownload(static function () use ($writer): void {
            $writer->save('php://output');
        }, $nama, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * File asli yang di-upload.
     */
    public function file(int $id): StreamedResponse
    {
        $upload = DmsRosterMasterUpload::findOrFail($id);
        abort_unless($upload->path_file !== '' && Storage::disk('local')->exists($upload->path_file), 404);

        return Storage::disk('local')->download($upload->path_file, $upload->nama_file);
    }

    /**
     * Gambar tanda tangan approver (disimpan di disk local, tidak publik).
     */
    public function ttd(int $id): \Symfony\Component\HttpFoundation\Response
    {
        $upload = DmsRosterMasterUpload::findOrFail($id);
        abort_unless($upload->ttd_path && Storage::disk('local')->exists($upload->ttd_path), 404);

        return Storage::disk('local')->response($upload->ttd_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function review(Request $request, int $id, bool $setuju): RedirectResponse
    {
        $data = $request->validate([
            'catatan_review' => [$setuju ? 'nullable' : 'required', 'string', 'max:1000'],
            // Persetujuan: tanda tangan digambar (data URL PNG) ATAU file gambar.
            'ttd_mode' => [$setuju ? 'required' : 'nullable', 'in:gambar,upload'],
            'ttd_data' => ['nullable', 'string', 'max:3000000'],
            'ttd_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
        ], [
            'catatan_review.required' => 'Alasan penolakan wajib diisi.',
            'ttd_mode.required' => 'Persetujuan wajib disertai tanda tangan.',
            'ttd_file.mimes' => 'File tanda tangan harus PNG atau JPG.',
            'ttd_file.max' => 'File tanda tangan maksimal 2 MB.',
        ]);
        $upload = DmsRosterMasterUpload::findOrFail($id);
        if (! DmsRosterAksesPerusahaan::mencakup($this->cakupan(), $this->akses->perusahaanUpload($upload->id))) {
            return back()->with('error', 'Anda hanya boleh '.($setuju ? 'menyetujui' : 'menolak')
                .' roster yang seluruh perusahaannya di-assign ke akun Anda (menu Assign Role).');
        }

        try {
            $ttd = null;
            if ($setuju) {
                if ($data['ttd_mode'] === 'upload' && ! $request->hasFile('ttd_file')) {
                    throw new RuntimeException('Pilih file gambar tanda tangan untuk di-upload.');
                }
                // Mode gambar mengirim PNG hasil canvas lewat ttd_file (ttd_data
                // hanya cadangan browser lama), jadi file dipakai di kedua mode.
                $ttd = $this->service->simpanTtd(
                    $upload,
                    $data['ttd_mode'] === 'gambar' ? ($data['ttd_data'] ?? null) : null,
                    $request->file('ttd_file'),
                    $data['ttd_mode'] === 'gambar',
                );
            }
            $this->service->review($upload, $setuju, $data['catatan_review'] ?? null, $this->pengguna(), $ttd);
        } catch (RuntimeException $e) {
            return back()->withInput($request->except('ttd_data', 'ttd_file'))->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dms.master-roster.show', $upload->id)
            ->with('success', 'Roster "'.$upload->judul.'" '.($setuju ? 'disetujui.' : 'ditolak.'));
    }

    /**
     * @param  list<string>|null  $cakupan  dipakai bila $batasi (halaman Approval)
     */
    private function daftar(Request $request, string $status, ?array $cakupan = null, bool $batasi = false): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $q = DmsRosterMasterUpload::query()->orderByDesc('created_at');
        if ($batasi) {
            $this->akses->batasiUpload($q, $cakupan);
        }
        if (in_array($status, [DmsRosterMasterUpload::STATUS_PENDING, DmsRosterMasterUpload::STATUS_APPROVED, DmsRosterMasterUpload::STATUS_REJECTED], true)) {
            $q->where('status', $status);
        }

        return $q->paginate(self::PER_HALAMAN)->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    private function ringkas(?array $cakupan = null, bool $batasi = false): array
    {
        $q = DmsRosterMasterUpload::query();
        if ($batasi) {
            $this->akses->batasiUpload($q, $cakupan);
        }
        $n = $q->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return [
            DmsRosterMasterUpload::STATUS_PENDING => (int) ($n[DmsRosterMasterUpload::STATUS_PENDING] ?? 0),
            DmsRosterMasterUpload::STATUS_APPROVED => (int) ($n[DmsRosterMasterUpload::STATUS_APPROVED] ?? 0),
            DmsRosterMasterUpload::STATUS_REJECTED => (int) ($n[DmsRosterMasterUpload::STATUS_REJECTED] ?? 0),
        ];
    }

    /**
     * @return list<string>|null null = semua perusahaan
     */
    private function cakupan(): ?array
    {
        $user = Auth::user();

        return $this->akses->cakupan($user instanceof User ? $user : null);
    }

    private function pengguna(): string
    {
        return mb_substr((string) (Auth::user()?->name ?? 'system'), 0, 150);
    }
}
