<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dms\Roster\DmsRosterAksesPerusahaan;
use App\Services\Dms\Roster\DmsRosterCakupanReader;
use App\Services\Dms\Roster\DmsRosterMasterEditService;
use App\Services\Dms\Roster\DmsRosterMasterService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
 *   '__semua' semua perusahaan
 *   <nama>    satu perusahaan (nama resmi, ejaan di roster digabung)
 */
class RosterMasterEditController extends Controller
{
    private const PER_HALAMAN = 25;

    public function __construct(
        private readonly DmsRosterMasterEditService $edit,
        private readonly DmsRosterAksesPerusahaan $akses,
        private readonly DmsRosterMasterService $master,
        private readonly DmsRosterCakupanReader $cakupan,
    ) {}

    public function index(Request $request): View
    {
        $assignment = $this->assignment();
        $ejaanSaya = DmsRosterAksesPerusahaan::ejaanCakupan($assignment);
        // Pilihan perusahaan: nama resmi + semua ejaannya di roster.
        $daftarPt = array_map(
            static fn (array $p): array => ['nama' => $p['perusahaan'], 'ejaan' => $p['ejaan']],
            $this->master->perusahaanTerupload($this->cakupan->peta()),
        );

        $pt = (string) $request->query('pt', '');
        $terpilih = collect($daftarPt)->firstWhere('nama', $pt);
        if (($terpilih === null && ! in_array($pt, ['__semua', '__saya'], true)) || ($pt === '__saya' && $assignment === [])) {
            $pt = $assignment === [] ? '__semua' : '__saya';
        }
        $ejaan = match ($pt) {
            '__semua' => null,
            '__saya' => $ejaanSaya,
            default => $terpilih['ejaan'],
        };
        $rentang = $ejaan === [] ? null : $this->edit->rentang($ejaan);

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

        $halaman = max(1, (int) $request->query('hal', 1));
        $cari = mb_substr(trim((string) $request->query('q', '')), 0, 50);
        $grid = $bulan === null ? ['karyawan' => [], 'total' => 0]
            : $this->edit->grid($ejaan, $bulan->startOfMonth(), $bulan->endOfMonth(), $cari, $halaman, self::PER_HALAMAN);
        // Baris yang boleh diedit: karyawan perusahaan yang di-assign ke user ini.
        $milikSaya = array_fill_keys(array_map(static fn (string $e): string => mb_strtoupper(trim($e)), $ejaanSaya), true);
        foreach ($grid['karyawan'] as &$k) {
            $k['boleh'] = isset($milikSaya[mb_strtoupper(trim((string) $k['perusahaan']))]);
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
        abort_if($assignment === [], 403, 'Edit roster hanya untuk user yang di-assign ke perusahaan di menu Assign Role.');

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

        try {
            $hasil = $this->edit->simpan(
                $normal($data['sel'] ?? [], ['sid', 'tanggal', 'kode']),
                $normal($data['jam'] ?? [], ['sid', 'masuk', 'keluar', 'total']),
                DmsRosterAksesPerusahaan::ejaanCakupan($assignment),
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
     * @return list<string>
     */
    private function assignment(): array
    {
        $user = Auth::user();

        return $this->akses->assignment($user instanceof User ? $user : null);
    }
}
