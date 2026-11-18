<?php

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master per baris (acuan utama, keputusan user 18 Nov 2026): tiap baris HIRA (aktivitas · sub · bahaya-risiko) dengan
 * tiga daftar label yang bisa diubah — Item Sumber Observasi (drm_master_teks OBS), Item Sumber OAK (drm_master_teks OAK,
 * "aktivitas / sub aktivitas"), Item Sumber Ketidaksesuaian (drm_master_label). Yang dihitung sync persis label ini.
 */
final class PetaRisikoMasterBarisController extends Controller
{
    private const PER_HALAMAN = 40;

    public function __construct(private readonly DrmMasterDb $db)
    {
    }

    public function index(Request $request): View
    {
        $siap = DrmMasterDb::siap();
        $data = ['siap' => $siap, 'terisi' => false, 'bolehUbah' => $this->bolehUbah($request), 'q' => trim((string) $request->query('q', '')),
            'kb' => (string) $request->query('kb', ''), 'kosong' => (string) $request->query('kosong', '')];
        if (! $siap || ! DrmMasterDb::terisi()) {
            return view('peta-risiko-pja.drm.master-baris', $data);
        }

        $query = DB::table('drm_master_baris')->where('aktif', true);
        if ($data['q'] !== '') {
            $pola = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $data['q']).'%';
            $query->where(function ($w) use ($pola, $data) {
                $w->where('aktivitas', 'like', $pola)->orWhere('sub', 'like', $pola)->orWhere('bahaya', 'like', $pola);
                if (ctype_digit($data['q'])) {
                    $w->orWhere('ri', (int) $data['q']);
                }
            });
        }
        if (in_array($data['kb'], ['Significant', 'High', 'Medium', 'Low'], true)) {
            $query->where('kb', $data['kb']);
        }
        // Filter baris yang belum punya label di salah satu sumber.
        if (in_array($data['kosong'], ['OBS', 'OAK'], true)) {
            $query->whereNotIn('ri', DB::table('drm_master_teks')->where('aktif', true)->where('jenis', $data['kosong'])->select('ri'));
        } elseif ($data['kosong'] === 'KET') {
            $query->whereNotIn('ri', DB::table('drm_master_label')->where('aktif', true)->select('ri'));
        } elseif ($data['kosong'] === 'SEMUA') {
            $query->whereNotIn('ri', DB::table('drm_master_teks')->where('aktif', true)->select('ri'))
                ->whereNotIn('ri', DB::table('drm_master_label')->where('aktif', true)->select('ri'));
        }
        $baris = $query->orderBy('aktivitas')->orderBy('sub')->orderBy('ri')->paginate(self::PER_HALAMAN)->withQueryString();
        $ri = collect($baris->items())->pluck('ri')->all();

        $adaSumber = Schema::hasColumn('drm_master_teks', 'sumber');
        $teks = DB::table('drm_master_teks')->where('aktif', true)->whereIn('ri', $ri)
            ->orderBy('teks')->get(['id', 'jenis', 'teks', 'ri', ...($adaSumber ? ['sumber'] : [])])->groupBy(fn ($t) => $t->ri.'|'.$t->jenis);
        $label = DB::table('drm_master_label')->where('aktif', true)->whereIn('ri', $ri)
            ->orderBy('ketidaksesuaian')->orderBy('subketidaksesuaian')->get(['id', 'ketidaksesuaian', 'subketidaksesuaian', 'ri', 'sumber'])->groupBy('ri');

        return view('peta-risiko-pja.drm.master-baris', $data + [
            'terisi' => true,
            'baris' => $baris,
            'teks' => $teks,
            'label' => $label,
            'jumlah' => [
                'baris' => DB::table('drm_master_baris')->where('aktif', true)->count(),
                'OBS' => DB::table('drm_master_teks')->where('aktif', true)->where('jenis', 'OBS')->distinct()->count('ri'),
                'OAK' => DB::table('drm_master_teks')->where('aktif', true)->where('jenis', 'OAK')->distinct()->count('ri'),
                'KET' => DB::table('drm_master_label')->where('aktif', true)->distinct()->count('ri'),
            ],
            'saranObs' => DB::table('drm_master_teks')->where('jenis', 'OBS')->distinct()->orderBy('teks')->limit(3000)->pluck('teks'),
            'saranOak' => DB::table('drm_master_teks')->where('jenis', 'OAK')->distinct()->orderBy('teks')->limit(3000)->pluck('teks'),
            'saranK' => DB::table('drm_master_label')->distinct()->orderBy('ketidaksesuaian')->limit(1000)->pluck('ketidaksesuaian'),
            'saranSk' => DB::table('drm_master_label')->distinct()->orderBy('subketidaksesuaian')->limit(3000)->pluck('subketidaksesuaian'),
        ]);
    }

    /** Tambah satu label ke baris: jenis OBS / OAK (teks) atau KET (ketidaksesuaian + subketidaksesuaian). */
    public function tambah(Request $request, int $ri): RedirectResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
        abort_unless(DB::table('drm_master_baris')->where('ri', $ri)->exists(), 404);
        $v = $request->validate([
            'jenis' => ['required', 'in:OBS,OAK,KET'],
            'teks' => ['required_unless:jenis,KET', 'nullable', 'string', 'max:500'],
            'k' => ['required_if:jenis,KET', 'nullable', 'string', 'max:300'],
            'sk' => ['required_if:jenis,KET', 'nullable', 'string', 'max:300'],
        ]);
        $sekarang = now();

        if ($v['jenis'] === 'KET') {
            [$k, $sk] = [trim((string) $v['k']), trim((string) $v['sk'])];
            $kunci = DrmMaster::norm($k).' / '.DrmMaster::norm($sk);
            $sama = DB::table('drm_master_label')->where('ri', $ri)->get()
                ->first(fn ($l) => DrmMaster::norm($l->ketidaksesuaian).' / '.DrmMaster::norm($l->subketidaksesuaian) === $kunci);
            if ($sama !== null) {
                $sama->aktif ? null : DB::table('drm_master_label')->where('id', $sama->id)->update(['aktif' => true, 'updated_at' => $sekarang]);

                return back()->with('pesan', 'Label sudah ada di baris ini'.($sama->aktif ? '.' : ' — diaktifkan kembali.'));
            }
            $nilai = ['ketidaksesuaian' => $k, 'subketidaksesuaian' => $sk, 'ri' => $ri, 'sumber' => 'manual', 'aktif' => true,
                'diubah_oleh' => $request->user()?->getAuthIdentifier(), 'created_at' => $sekarang, 'updated_at' => $sekarang];
            $id = DB::table('drm_master_label')->insertGetId($nilai);
            $this->db->catat('drm_master_label', (string) $id, 'tambah', null, $nilai);

            return back()->with('pesan', 'Label ketidaksesuaian ditambahkan ke baris '.$ri.'.');
        }

        $teks = trim((string) $v['teks']);
        $sama = DB::table('drm_master_teks')->where('ri', $ri)->where('jenis', $v['jenis'])->get()
            ->first(fn ($t) => DrmMaster::crcTeks($t->teks) === DrmMaster::crcTeks($teks));
        if ($sama !== null) {
            $sama->aktif ? null : DB::table('drm_master_teks')->where('id', $sama->id)->update(['aktif' => true, 'updated_at' => $sekarang]);

            return back()->with('pesan', 'Label sudah ada di baris ini'.($sama->aktif ? '.' : ' — diaktifkan kembali.'));
        }
        $nilai = ['jenis' => $v['jenis'], 'teks' => $teks, 'ri' => $ri, 'aktif' => true,
            'diubah_oleh' => $request->user()?->getAuthIdentifier(), 'created_at' => $sekarang, 'updated_at' => $sekarang]
            + (Schema::hasColumn('drm_master_teks', 'sumber') ? ['sumber' => 'manual'] : []);
        $id = DB::table('drm_master_teks')->insertGetId($nilai);
        $this->db->catat('drm_master_teks', (string) $id, 'tambah', null, $nilai);

        return back()->with('pesan', 'Label '.$v['jenis'].' ditambahkan ke baris '.$ri.'.');
    }

    /** Hapus satu label dari baris (jenis teks = OBS/OAK, label = ketidaksesuaian). Tercatat di riwayat. */
    public function hapus(Request $request, string $jenis, int $id): RedirectResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
        $tabel = ['teks' => 'drm_master_teks', 'label' => 'drm_master_label'][$jenis] ?? abort(404);
        $lama = DB::table($tabel)->where('id', $id)->first() ?? abort(404);
        DB::table($tabel)->where('id', $id)->delete();
        $this->db->catat($tabel, (string) $id, 'hapus', (array) $lama, null);

        return back()->with('pesan', 'Label dihapus dari baris '.$lama->ri.'.');
    }

    private function bolehUbah(Request $request): bool
    {
        $editor = (array) config('peta_risiko_drm.master.editor', []);
        $user = $request->user();

        return $user !== null && ($editor === [] || in_array((string) $user->getAuthIdentifier(), $editor, true)
            || in_array((string) ($user->email ?? ''), $editor, true));
    }
}
