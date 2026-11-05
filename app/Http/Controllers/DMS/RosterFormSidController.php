<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Services\Dms\Roster\DmsRosterMasterEditService;
use App\Services\Dms\Roster\DmsRosterMasterService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Form Edit Roster per SID (/dms/master-roster/edit/form): TERBUKA TANPA LOGIN,
 * konsepnya sama dengan form absensi Control Room. Cari SID → jadwal hari ini
 * ± 7 hari → ubah kode per tanggal → baris jadwalnya langsung diperbarui.
 *
 * Satu kiriman hanya mengubah satu SID, rentang tanggal dihitung ulang di
 * server, dan tidak ada opsi kosongkan. Tabel Edit Roster tidak terpengaruh.
 */
final class RosterFormSidController extends Controller
{
    public function __construct(
        private readonly DmsRosterMasterEditService $edit,
    ) {}

    public function index(): View
    {
        return view('dms.roster-master.form-sid', [
            'hariIni' => CarbonImmutable::now()->startOfDay(),
            'kode' => array_map(static fn (array $k): string => $k[0], DmsRosterMasterService::KODE),
        ]);
    }

    public function cari(Request $request): JsonResponse
    {
        $data = $request->validate(['sid' => ['required', 'string', 'max:50']], ['sid.required' => 'SID wajib diisi.']);

        try {
            $jadwal = $this->edit->jadwalSid(self::sid($data['sid']), CarbonImmutable::now()->startOfDay());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'galat' => ['Data roster gagal dibaca. Coba lagi beberapa saat.']], 500);
        }

        return $jadwal === null
            ? response()->json(['ok' => false, 'galat' => ['SID ini belum ada di roster mana pun. Upload roster dulu, atau periksa kembali SID-nya.']], 404)
            : response()->json(['ok' => true, 'data' => $jadwal]);
    }

    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sid' => ['required', 'string', 'max:50'],
            'sel' => ['required', 'array', 'min:1', 'max:'.(DmsRosterMasterEditService::RENTANG_FORM_HARI * 2 + 1)],
            'sel.*.tanggal' => ['required', 'date_format:Y-m-d'],
            'sel.*.kode' => ['required', 'string', Rule::in(array_keys(DmsRosterMasterService::KODE))],
        ], [
            'sel.required' => 'Belum ada tanggal yang diubah.',
            'sel.*.kode.in' => 'Kode roster tidak dikenal.',
        ]);

        $hariIni = CarbonImmutable::now()->startOfDay();
        [$dari, $sampai] = DmsRosterMasterEditService::rentangForm($hariIni);

        try {
            $jadwal = $this->edit->jadwalSid(self::sid($data['sid']), $hariIni);
            if ($jadwal === null) {
                return response()->json(['ok' => false, 'galat' => ['SID ini belum ada di roster mana pun.']], 422);
            }

            $sid = $jadwal['sid'];
            $hasil = $this->edit->simpan(
                array_map(static fn (array $s): array => ['sid' => $sid, 'tanggal' => $s['tanggal'], 'kode' => $s['kode']], $data['sel']),
                [],
                null,
                $this->pengubah($request, $sid),
                $dari,
                $sampai,
            );
            if ($hasil['galat'] !== []) {
                return response()->json(['ok' => false, 'galat' => $hasil['galat']], 422);
            }

            return response()->json([
                'ok' => true,
                'pesan' => $hasil['ubah'] === 0 ? 'Tidak ada perubahan.' : $hasil['ubah'].' tanggal diperbarui.',
                'data' => $this->edit->jadwalSid($sid, $hariIni),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'galat' => ['Gagal menyimpan. Coba lagi beberapa saat.']], 500);
        }
    }

    private static function sid(string $sid): string
    {
        return mb_strtoupper(trim($sid));
    }

    /** Pengisi form tidak harus login: riwayat edit mencatat SID & IP-nya. */
    private function pengubah(Request $request, string $sid): string
    {
        return Auth::user()?->name ?? 'Form SID '.$sid.' ('.$request->ip().')';
    }
}
