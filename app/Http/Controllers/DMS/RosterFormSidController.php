<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Services\Dms\Roster\DmsRosterMasterEditService;
use App\Services\Dms\Roster\DmsRosterMasterService;
use App\Services\Dms\Roster\DmsRosterPengajuanService;
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
 * ± 7 hari → ajukan kode baru per tanggal. Jadwal baru berubah setelah
 * pengajuannya disetujui di menu Approval Pengajuan Edit Roster.
 *
 * Satu kiriman hanya untuk satu SID, rentang tanggal dihitung ulang di
 * server, dan tidak ada opsi kosongkan. Tabel Edit Roster tidak terpengaruh.
 */
final class RosterFormSidController extends Controller
{
    public function __construct(
        private readonly DmsRosterMasterEditService $edit,
        private readonly DmsRosterPengajuanService $pengajuan,
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

            return $jadwal === null
                ? response()->json(['ok' => false, 'galat' => ['SID ini belum ada di roster mana pun. Upload roster dulu, atau periksa kembali SID-nya.']], 404)
                : response()->json(['ok' => true, 'data' => $this->denganMenunggu($jadwal)]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'galat' => ['Data roster gagal dibaca. Coba lagi beberapa saat.']], 500);
        }
    }

    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sid' => ['required', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'sel' => ['required', 'array', 'min:1', 'max:'.(DmsRosterMasterEditService::RENTANG_FORM_HARI * 2 + 1)],
            'sel.*.tanggal' => ['required', 'date_format:Y-m-d'],
            'sel.*.kode' => ['required', 'string', Rule::in(array_keys(DmsRosterMasterService::KODE))],
        ], [
            'sel.required' => 'Belum ada tanggal yang diubah.',
            'sel.*.kode.in' => 'Kode roster tidak dikenal.',
        ]);

        if (! DmsRosterPengajuanService::adaTabel()) {
            return response()->json(['ok' => false, 'galat' => ['Pengajuan belum bisa dikirim: fitur ini belum aktif di server. Hubungi admin.']], 503);
        }

        $hariIni = CarbonImmutable::now()->startOfDay();

        try {
            $jadwal = $this->edit->jadwalSid(self::sid($data['sid']), $hariIni);
            if ($jadwal === null) {
                return response()->json(['ok' => false, 'galat' => ['SID ini belum ada di roster mana pun.']], 422);
            }

            $hasil = $this->pengajuan->ajukan(
                $jadwal,
                array_map(static fn (array $s): array => ['tanggal' => $s['tanggal'], 'kode' => $s['kode']], $data['sel']),
                $data['catatan'] ?? null,
                $this->pengaju($request, $jadwal['sid']),
            );
            if ($hasil['galat'] !== []) {
                return response()->json(['ok' => false, 'galat' => $hasil['galat']], 422);
            }

            return response()->json([
                'ok' => true,
                'pesan' => 'Pengajuan #'.$hasil['pengajuan']->id.' terkirim: '.count($hasil['pengajuan']->perubahan).' tanggal menunggu approval. Jadwal berubah setelah disetujui.',
                'data' => $this->denganMenunggu($jadwal),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'galat' => ['Gagal mengirim pengajuan. Coba lagi beberapa saat.']], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $jadwal
     * @return array<string, mixed>
     */
    private function denganMenunggu(array $jadwal): array
    {
        // Objek JSON, bukan [] saat kosong — dibaca per tanggal di browser.
        return $jadwal + ['diajukan' => (object) $this->pengajuan->menunggu($jadwal['sid'])];
    }

    private static function sid(string $sid): string
    {
        return mb_strtoupper(trim($sid));
    }

    /** Pengisi form tidak harus login: yang dicatat SID & IP-nya. */
    private function pengaju(Request $request, string $sid): string
    {
        return Auth::user()?->name ?? 'Form SID '.$sid.' ('.$request->ip().')';
    }
}
