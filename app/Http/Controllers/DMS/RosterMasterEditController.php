<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dms\Roster\DmsRosterAksesPerusahaan;
use App\Services\Dms\Roster\DmsRosterApproverDepartemen;
use App\Services\Dms\Roster\DmsRosterCakupanReader;
use App\Services\Dms\Roster\DmsRosterMasterEditService;
use App\Services\Dms\Roster\DmsRosterMasterService;
use App\Services\Dms\Roster\DmsRosterSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Master Roster → Edit Roster: satu tabel gabungan semua roster yang
 * di-upload (bila dobel, upload terbaru yang dipakai), per bulan.
 *
 * Semua user boleh MELIHAT seluruh perusahaan. MENGEDIT hanya baris karyawan
 * perusahaan yang di-assign ke user itu di menu Assign Role — admin tanpa
 * assignment pun hanya melihat. Simpan ditolak untuk SID di luar perusahaan
 * user (DmsRosterMasterEditService::simpan). Filter perusahaan:
 *   ''        bawaan: perusahaan saya (bila di-assign), kalau tidak semua
 *   '__saya'  semua perusahaan yang di-assign ke user
 *   '__dept'  manager departemen PT Berau Coal (config approver_departemen):
 *             hanya karyawan departemennya, dan baris itu boleh ia edit
 *   '__semua' semua perusahaan
 *   <nama>    satu perusahaan (nama resmi, ejaan di roster digabung)
 */
class RosterMasterEditController extends Controller
{
    private const PER_HALAMAN = 25;

    /** Download: diambil per 500 SID supaya query tetap kecil. */
    private const PER_HALAMAN_UNDUH = 500;

    /** Batas baris per file download (±3.700 = perusahaan terbesar). */
    private const MAKS_UNDUH = 5000;

    public function __construct(
        private readonly DmsRosterMasterEditService $edit,
        private readonly DmsRosterAksesPerusahaan $akses,
        private readonly DmsRosterMasterService $master,
        private readonly DmsRosterCakupanReader $cakupan,
        private readonly DmsRosterApproverDepartemen $approverDepartemen,
    ) {}

    public function index(Request $request): View
    {
        [
            'assignment' => $assignment, 'ejaanSaya' => $ejaanSaya, 'daftarPt' => $daftarPt, 'pt' => $pt,
            'ejaan' => $ejaan, 'rentang' => $rentang, 'bulan' => $bulan, 'cari' => $cari,
            'departemen' => $departemen, 'sidDepartemen' => $sidDepartemen, 'hanyaSid' => $hanyaSid,
        ] = $this->filter($request);

        $halaman = max(1, (int) $request->query('hal', 1));
        $grid = $bulan === null ? ['karyawan' => [], 'total' => 0]
            : $this->edit->grid($ejaan, $bulan->startOfMonth(), $bulan->endOfMonth(), $cari, $halaman, self::PER_HALAMAN, '', $hanyaSid);
        // Baris yang boleh diedit: karyawan perusahaan yang di-assign ke user ini,
        // atau (manager departemen BC) karyawan departemennya.
        $milikSaya = array_fill_keys(array_map(static fn (string $e): string => mb_strtoupper(trim($e)), $ejaanSaya), true);
        $sidSaya = array_fill_keys($sidDepartemen, true);
        foreach ($grid['karyawan'] as &$k) {
            $k['boleh'] = isset($milikSaya[mb_strtoupper(trim((string) $k['perusahaan']))])
                || isset($sidSaya[mb_strtoupper(trim((string) $k['sid']))]);
        }
        unset($k);

        $tanggal = [];
        if ($bulan !== null) {
            for ($t = $bulan->startOfMonth(); $t->lte($bulan->endOfMonth()); $t = $t->addDay()) {
                $tanggal[] = $t;
            }
        }

        return view('dms.roster-master.edit', [
            'assignment' => $assignment,
            'departemen' => $departemen,
            'pt' => $pt,
            'daftarPt' => array_column($daftarPt, 'nama'),
            'bolehEdit' => in_array(true, array_column($grid['karyawan'], 'boleh'), true),
            'rentang' => $rentang,
            'bulan' => $bulan,
            'grid' => $grid,
            'tanggal' => $tanggal,
            'halaman' => $halaman,
            'perHalaman' => self::PER_HALAMAN,
            'totalHalaman' => max(1, (int) ceil($grid['total'] / self::PER_HALAMAN)),
            'cari' => $cari,
            'riwayat' => $this->edit->riwayat($ejaan),
            'adaLog' => DmsRosterMasterEditService::adaLog(),
        ]);
    }

    /**
     * Simpan perubahan dari editor (JSON):
     *   sel: [{sid, tanggal, kode}]  kode '' = kosongkan
     *   jam: [{sid, masuk, keluar, total}]
     */
    public function update(Request $request): JsonResponse
    {
        $assignment = $this->assignment();
        $sidDepartemen = $this->sidDepartemen();
        abort_if($assignment === [] && $sidDepartemen === [], 403, 'Edit roster hanya untuk user yang di-assign ke perusahaan di menu Assign Role atau manager departemen PT Berau Coal.');

        $data = $request->validate([
            'sel' => ['array', 'max:'.DmsRosterMasterEditService::MAKS_PERUBAHAN],
            'sel.*.sid' => ['required', 'string', 'max:50'],
            'sel.*.tanggal' => ['required', 'date_format:Y-m-d'],
            'sel.*.kode' => ['nullable', 'string', 'max:10'],
            'jam' => ['array', 'max:500'],
            'jam.*.sid' => ['required', 'string', 'max:50'],
            'jam.*.masuk' => ['nullable', 'string', 'max:5'],
            'jam.*.keluar' => ['nullable', 'string', 'max:5'],
            'jam.*.total' => ['nullable', 'string', 'max:6'],
        ]);
        $normal = static fn (array $baris, array $kolom): array => array_map(
            static fn (array $r): array => array_map(static fn ($v): string => trim((string) ($v ?? '')), array_intersect_key($r, array_flip($kolom)) + array_fill_keys($kolom, '')),
            $baris,
        );

        $sel = $normal($data['sel'] ?? [], ['sid', 'tanggal', 'kode']);
        $jam = $normal($data['jam'] ?? [], ['sid', 'masuk', 'keluar', 'total']);
        $cakupan = DmsRosterAksesPerusahaan::ejaanCakupan($assignment);
        if ($assignment === []) {
            // Manager departemen: hanya SID karyawan departemennya, roster PT Berau Coal.
            $boleh = array_fill_keys($sidDepartemen, true);
            $luar = array_values(array_unique(array_filter(
                array_merge(array_column($sel, 'sid'), array_column($jam, 'sid')),
                static fn (string $sid): bool => ! isset($boleh[mb_strtoupper($sid)]),
            )));
            if ($luar !== []) {
                return response()->json(['ok' => false, 'galat' => ['SID di luar departemen Anda: '.implode(', ', array_slice($luar, 0, 10))]], 403);
            }
            $cakupan = $this->ejaanBerauCoal();
        }

        try {
            $hasil = $this->edit->simpan(
                $sel,
                $jam,
                $cakupan,
                mb_substr((string) (Auth::user()?->name ?? 'system'), 0, 150),
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'galat' => ['Gagal menyimpan: '.$e->getMessage()]], 500);
        }

        if ($hasil['galat'] !== []) {
            return response()->json(['ok' => false, 'galat' => $hasil['galat']], 422);
        }

        return response()->json([
            'ok' => true,
            'ubah' => $hasil['ubah'],
            'pesan' => $hasil['ubah'] === 0 ? 'Tidak ada perubahan.' : number_format($hasil['ubah'], 0, ',', '.').' jadwal diperbarui.',
        ]);
    }

    /**
     * Download Excel tabel gabungan dengan filter yang sedang tampil (perusahaan,
     * bulan, kata kunci) — semua halaman, bukan hanya halaman yang terlihat.
     * Formatnya template download roster (DmsRosterMasterService::template)
     * yang sudah terisi kode & jam kerja, jadi bisa langsung di-upload ulang.
     */
    public function unduh(Request $request): StreamedResponse|RedirectResponse
    {
        ['pt' => $pt, 'ejaan' => $ejaan, 'bulan' => $bulan, 'cari' => $cari, 'hanyaSid' => $hanyaSid] = $this->filter($request);
        $kembali = static fn (string $pesan): RedirectResponse => redirect()
            ->route('dms.master-roster.edit.index', array_filter($request->only(['pt', 'bulan', 'q'])))
            ->with('error', $pesan);
        if ($bulan === null) {
            return $kembali('Belum ada roster untuk di-download.');
        }

        $dari = $bulan->startOfMonth();
        $sampai = $bulan->endOfMonth();
        $karyawan = [];
        $halaman = 1;
        do {
            $grid = $this->edit->grid($ejaan, $dari, $sampai, $cari, $halaman, self::PER_HALAMAN_UNDUH, '', $hanyaSid);
            if ($grid['total'] > self::MAKS_UNDUH) {
                return $kembali('Terlalu banyak untuk satu file: '.number_format($grid['total'], 0, ',', '.')
                    .' karyawan (maks '.number_format(self::MAKS_UNDUH, 0, ',', '.').'). Pilih satu perusahaan atau persempit pencarian.');
            }
            foreach ($grid['karyawan'] as $k) {
                $karyawan[] = [
                    'sid' => (string) $k['sid'],
                    'nama' => (string) $k['nama'],
                    'perusahaan' => (string) $k['perusahaan'],
                    'jabatan' => (string) $k['jabatan'],
                    'site' => (string) $k['site'],
                    'jadwal' => array_map(static fn (array $h): string => (string) $h['kode'], $k['jadwal']),
                    'jam_awal' => $k['jam_awal'],
                    'jam_akhir' => $k['jam_akhir'],
                    'jam_total' => $k['jam_total'],
                ];
            }
            $halaman++;
        } while (count($karyawan) < $grid['total'] && $grid['karyawan'] !== []);

        if ($karyawan === []) {
            return $kembali('Tidak ada karyawan dengan jadwal di bulan ini'.($cari !== '' ? ' untuk kata kunci "'.$cari.'"' : '').'.');
        }

        // Ribuan baris × 31 hari: PhpSpreadsheet butuh memori lebih dari bawaan.
        // Hanya dinaikkan, tidak pernah menurunkan batas server yang sudah lebih besar.
        @set_time_limit(300);
        $batas = (string) ini_get('memory_limit');
        if ($batas !== '-1' && (function_exists('ini_parse_quantity') ? ini_parse_quantity($batas) : (int) $batas) < 1024 ** 3) {
            ini_set('memory_limit', '1024M');
        }
        $writer = new Xlsx($this->master->template($dari, $sampai, $karyawan));
        $label = match ($pt) {
            '__semua' => 'semua',
            '__saya' => 'perusahaan_saya',
            default => DmsRosterSyncService::kodePt($pt),
        };
        $nama = 'roster_'.Str::slug($label, '_').'_'.$bulan->format('Y-m').($cari !== '' ? '_'.Str::slug($cari, '_') : '').'.xlsx';

        return response()->streamDownload(static function () use ($writer): void {
            $writer->save('php://output');
        }, $nama, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Filter halaman Edit Roster dari query string, dipakai index & download.
     *
     * @return array{assignment:list<string>,ejaanSaya:list<string>,daftarPt:list<array{nama:string,ejaan:list<string>}>,pt:string,ejaan:?list<string>,rentang:?array{dari:CarbonImmutable,sampai:CarbonImmutable},bulan:?CarbonImmutable,cari:string}
     */
    private function filter(Request $request): array
    {
        $assignment = $this->assignment();
        $ejaanSaya = DmsRosterAksesPerusahaan::ejaanCakupan($assignment);
        // Pilihan perusahaan: nama resmi + semua ejaannya di roster.
        $daftarPt = array_map(
            static fn (array $p): array => ['nama' => $p['perusahaan'], 'ejaan' => $p['ejaan']],
            $this->master->perusahaanTerupload($this->cakupan->peta()),
        );

        $user = Auth::user();
        $departemen = $this->approverDepartemen->departemenUser($user instanceof User ? $user : null);
        $sidDepartemen = $this->sidDepartemen();

        $pt = (string) $request->query('pt', '');
        $terpilih = collect($daftarPt)->firstWhere('nama', $pt);
        $valid = $terpilih !== null || $pt === '__semua' || ($pt === '__saya' && $assignment !== []) || ($pt === '__dept' && $departemen !== null);
        if (! $valid) {
            // Bawaan: perusahaan saya (Assign Role), lalu departemen saya (manager BC), lalu semua.
            $pt = $assignment !== [] ? '__saya' : ($departemen !== null ? '__dept' : '__semua');
        }
        $hanyaSid = $pt === '__dept' ? $sidDepartemen : null;
        $ejaan = match ($pt) {
            '__semua' => null,
            '__saya' => $ejaanSaya,
            '__dept' => $this->ejaanBerauCoal($daftarPt),
            default => $terpilih['ejaan'],
        };
        $rentang = $ejaan === [] ? null : $this->edit->rentang($ejaan, $hanyaSid);

        // Bulan bawaan: bulan ini bila ada di rentang roster, kalau tidak bulan terakhirnya.
        $bulan = null;
        if ($rentang !== null) {
            $awal = $rentang['dari']->startOfMonth();
            $akhir = $rentang['sampai']->startOfMonth();
            $minta = (string) $request->query('bulan', '');
            $bulan = preg_match('/^\d{4}-\d{2}$/', $minta) === 1 ? CarbonImmutable::createFromFormat('!Y-m', $minta) : null;
            if ($bulan === null || $bulan->lt($awal) || $bulan->gt($akhir)) {
                $kini = CarbonImmutable::now()->startOfMonth();
                $bulan = $kini->betweenIncluded($awal, $akhir) ? $kini : $akhir;
            }
        }

        return [
            'assignment' => $assignment,
            'ejaanSaya' => $ejaanSaya,
            'daftarPt' => $daftarPt,
            'pt' => $pt,
            'ejaan' => $ejaan,
            'rentang' => $rentang,
            'bulan' => $bulan,
            'cari' => mb_substr(trim((string) $request->query('q', '')), 0, 50),
            'departemen' => $departemen,
            'sidDepartemen' => $sidDepartemen,
            'hanyaSid' => $hanyaSid,
        ];
    }

    /**
     * SID karyawan departemen akun ini bila ia manager departemen PT Berau Coal
     * (config approver_departemen; departemen per SID dari cache Tahap 10 sync).
     *
     * @return list<string> huruf besar
     */
    private function sidDepartemen(): array
    {
        $user = Auth::user();

        return array_values(array_map(
            static fn ($s): string => mb_strtoupper(trim((string) $s)),
            $this->approverDepartemen->sidUntuk($user instanceof User ? $user : null),
        ));
    }

    /**
     * Semua ejaan nama PT Berau Coal di roster.
     *
     * @param  list<array{nama:string,ejaan:list<string>}>|null  $daftarPt
     * @return list<string>
     */
    private function ejaanBerauCoal(?array $daftarPt = null): array
    {
        $nama = (string) config('dms_roster.master.approver_departemen.perusahaan', 'PT Berau Coal');
        $daftarPt ??= array_map(
            static fn (array $p): array => ['nama' => $p['perusahaan'], 'ejaan' => $p['ejaan']],
            $this->master->perusahaanTerupload($this->cakupan->peta()),
        );
        $p = collect($daftarPt)->firstWhere('nama', $nama);

        return $p['ejaan'] ?? [$nama];
    }

    /**
     * @return list<string>
     */
    private function assignment(): array
    {
        $user = Auth::user();

        return $this->akses->assignment($user instanceof User ? $user : null);
    }
}
