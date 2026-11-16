<?php

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\DrmRepository;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use App\Services\PetaRisiko\Drm\Master\DrmMasterSkema;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Halaman /peta-risiko-pja/master: koreksi tabel master mapping DRM 2.1 (baseline HIRA → grup → detail lokasi →
 * PJA, label SAP, teks OBS/OAK, tema). Generik mengikuti DrmMasterSkema. Perubahan berlaku pada sync berikutnya.
 */
class PetaRisikoMasterController extends Controller
{
    private const PER_HALAMAN = 50;

    public function __construct(private readonly DrmMasterDb $db, private readonly DrmRepository $repo)
    {
    }

    public function index(Request $request): View
    {
        $kode = (string) $request->query('t', 'baris');
        $skema = DrmMasterSkema::satu($kode) ?? abort(404);
        $siap = DrmMasterDb::siap();
        $data = ['skema' => $skema, 'kode' => $kode, 'semua' => DrmMasterSkema::semua(), 'siap' => $siap, 'terisi' => false,
            'bolehUbah' => $this->bolehUbah($request), 'q' => trim((string) $request->query('q', ''))];
        if (! $siap) {
            return view('peta-risiko-pja.drm.master', $data);
        }

        $query = DB::table($skema['tabel']);
        if ($data['q'] !== '') {
            // Cari di semua kolom teks + kolom cari; angka murni juga dicocokkan persis ke kolom angka (ID).
            $pola = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $data['q']).'%';
            $kolomTeks = array_keys(array_filter($skema['kolom'], fn ($k) => in_array($k[1], ['teks', 'teks_panjang'], true)));
            $query->where(function ($w) use ($skema, $data, $pola, $kolomTeks) {
                foreach (array_unique([...$skema['cari'], ...$kolomTeks]) as $kolom) {
                    $w->orWhere($kolom, 'like', $pola);
                }
                if (ctype_digit($data['q'])) {
                    foreach ($skema['kolom'] as $kolom => [, $tipe]) {
                        if ($tipe === 'int' || ($tipe === 'pilih' && $kolom !== 'kelompok')) {
                            $w->orWhere($kolom, (int) $data['q']);
                        }
                    }
                }
            });
        }
        // Filter dropdown per kolom (bool / pilihan): f[kolom]=nilai.
        $filter = array_intersect_key(array_filter((array) $request->query('f', []), fn ($v) => $v !== null && $v !== ''), $this->kolomFilter($skema));
        foreach ($filter as $kolom => $v) {
            $query->where($kolom, $skema['kolom'][$kolom][1] === 'bool' ? (int) (bool) $v : $v);
        }
        $data['filter'] = $filter;
        $data['kolomFilter'] = $this->kolomFilter($skema);
        $baris = $query->orderBy($skema['urut'])->orderBy($skema['kunci'])->paginate(self::PER_HALAMAN)->withQueryString();

        $ubah = null;
        if ($request->filled('ubah')) {
            $ubah = DB::table($skema['tabel'])->where($skema['kunci'], $request->query('ubah'))->first();
        }
        $riTampil = collect($baris->items())->pluck('ri')->push($ubah->ri ?? null)->filter(fn ($v) => $v !== null)->unique()->all();

        $meta = $this->repo->meta();
        $ubahTerakhir = DB::table('drm_master_log')->where('aksi', '!=', 'isi')->max('dibuat');

        return view('peta-risiko-pja.drm.master', $data + [
            'terisi' => DrmMasterDb::terisi(),
            'baris' => $baris,
            'ubah' => $ubah,
            'tambah' => $request->boolean('tambah'),
            'jumlah' => collect(DrmMasterSkema::semua())->map(fn ($s) => DB::table($s['tabel'])->count())->all(),
            'teksRi' => $riTampil ? DB::table('drm_master_baris')->whereIn('ri', $riTampil)->get()->keyBy('ri') : collect(),
            'pilihan' => $this->pilihan(),
            'saran' => $this->saran($skema),
            'log' => DB::table('drm_master_log')->orderByDesc('id')->limit(15)->get(),
            'syncTerakhir' => $meta['dibuat'] ?? null,
            'adaPerubahanBaru' => $ubahTerakhir && (! isset($meta['dibuat']) || strtotime((string) $ubahTerakhir) > strtotime((string) $meta['dibuat'])),
        ]);
    }

    public function simpan(Request $request, string $kode): RedirectResponse
    {
        $skema = DrmMasterSkema::satu($kode) ?? abort(404);
        abort_unless(DrmMasterDb::siap(), 404);
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');

        $id = $request->input('_id');
        $aturan = [];
        foreach ($skema['kolom'] as $kolom => [, $tipe, $rules, $opsi]) {
            if (! empty($opsi['kunci']) && $id !== null) {
                continue; // kunci manual tidak bisa diubah setelah dibuat
            }
            if (! empty($opsi['kunci'])) {
                $rules[] = Rule::unique($skema['tabel'], $kolom);
            }
            if (($opsi['pilihan'] ?? null) === 'kelompok') {
                $rules[] = Rule::exists('drm_master_kelompok', 'kode');
            }
            if (($opsi['pilihan'] ?? null) === 'grup') {
                $rules[] = Rule::exists('drm_master_grup', 'gid');
            }
            if (($opsi['pilihan'] ?? null) === 'tema') {
                $rules[] = Rule::exists('drm_master_tema', 'id');
            }
            $aturan[$kolom] = $tipe === 'bool' ? ['nullable', 'boolean'] : $rules;
        }
        $nilai = $request->validate($aturan, [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute harus angka.',
            'unique' => ':attribute sudah dipakai.',
            'exists' => ':attribute tidak ditemukan di tabel acuannya.',
            'ri.exists' => 'ID baris HIRA tidak ada di tabel Baris HIRA (M01).',
            'in' => 'Pilihan :attribute tidak valid.',
            'regex' => 'Format :attribute tidak valid.',
            'max' => ':attribute terlalu panjang.',
        ], array_map(fn ($k) => $k[0], $skema['kolom']));
        foreach ($skema['kolom'] as $kolom => [, $tipe]) {
            if ($tipe === 'bool' && array_key_exists($kolom, $aturan)) {
                $nilai[$kolom] = $request->boolean($kolom);
            }
            if ($tipe !== 'bool' && array_key_exists($kolom, $nilai) && $nilai[$kolom] === null) {
                $nilai[$kolom] = '';
            }
        }
        $nilai['diubah_oleh'] = $request->user()?->getAuthIdentifier();
        $nilai['updated_at'] = now();

        try {
            if ($id !== null) {
                $lama = DB::table($skema['tabel'])->where($skema['kunci'], $id)->first() ?? abort(404);
                DB::table($skema['tabel'])->where($skema['kunci'], $id)->update($nilai);
                $this->db->catat($skema['tabel'], (string) $id, 'ubah', (array) $lama, $nilai);
                $kunci = $id;
            } else {
                $nilai['created_at'] = now();
                if ($skema['kunciManual']) {
                    DB::table($skema['tabel'])->insert($nilai);
                    $kunci = $nilai[$skema['kunci']];
                } else {
                    $kunci = DB::table($skema['tabel'])->insertGetId($nilai);
                }
                $this->db->catat($skema['tabel'], (string) $kunci, 'tambah', null, $nilai);
            }
        } catch (QueryException $e) {
            report($e);

            return back()->withInput()->withErrors(['_umum' => str_contains($e->getMessage(), 'Duplicate')
                ? 'Data yang sama sudah ada (pasangan/kunci harus unik).' : 'Gagal menyimpan ke database.']);
        }

        return redirect()->route('peta-risiko-pja.master.index', ['t' => $kode, 'q' => $request->input('_q')])
            ->with('pesan', 'Tersimpan ('.$skema['judul'].' · '.$kunci.'). Berlaku setelah peta-risiko:sync berikutnya.');
    }

    public function hapus(Request $request, string $kode, string $id): RedirectResponse
    {
        $skema = DrmMasterSkema::satu($kode) ?? abort(404);
        abort_unless(DrmMasterDb::siap(), 404);
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');

        $lama = DB::table($skema['tabel'])->where($skema['kunci'], $id)->first() ?? abort(404);
        DB::table($skema['tabel'])->where($skema['kunci'], $id)->delete();
        $this->db->catat($skema['tabel'], $id, 'hapus', (array) $lama, null);

        return redirect()->route('peta-risiko-pja.master.index', ['t' => $kode, 'q' => $request->input('_q')])
            ->with('pesan', 'Dihapus ('.$skema['judul'].' · '.$id.'). Berlaku setelah peta-risiko:sync berikutnya.');
    }

    public function log(Request $request): View
    {
        abort_unless(DrmMasterDb::siap(), 404);

        return view('peta-risiko-pja.drm.master-log', [
            'log' => DB::table('drm_master_log')->orderByDesc('id')->paginate(50),
            'semua' => DrmMasterSkema::semua(),
        ]);
    }

    /**
     * Kolom yang bisa difilter lewat dropdown: bool dan pilihan.
     *
     * @param  array<string, mixed>  $skema
     * @return array<string, array<int, mixed>>
     */
    private function kolomFilter(array $skema): array
    {
        return array_filter($skema['kolom'], fn ($k) => in_array($k[1], ['bool', 'pilih'], true));
    }

    /** @return array<string, array<string, string>> */
    private function pilihan(): array
    {
        return [
            'kelompok' => DB::table('drm_master_kelompok')->orderBy('urutan')->get()->mapWithKeys(fn ($k) => [$k->kode => $k->kode.' · '.$k->nama])->all(),
            'grup' => DB::table('drm_master_grup')->orderBy('gid')->get()->mapWithKeys(fn ($g) => [(string) $g->gid => $g->gid.' · '.$g->nama])->all(),
            'tema' => DB::table('drm_master_tema')->orderBy('id')->get()->mapWithKeys(fn ($t) => [(string) $t->id => $t->id.' · '.$t->nama])->all(),
        ];
    }

    /**
     * Daftar saran dropdown untuk form tab aktif:
     *   kolom => [nilai unik] (opsi 'saran' tabel.kolom), 'ri' => [[ri, teks]] bila ada kolom baris HIRA,
     *   'aid' => [aktivitas => aid] bila ada kolom isiAid.
     *
     * @param  array<string, mixed>  $skema
     * @return array<string, mixed>
     */
    private function saran(array $skema): array
    {
        $hasil = [];
        foreach ($skema['kolom'] as $kolom => [, , , $opsi]) {
            if (isset($opsi['saran'])) {
                [$tabel, $k] = explode('.', $opsi['saran']);
                $hasil[$kolom] = DB::table($tabel)->where($k, '!=', '')->distinct()->orderBy($k)->limit(5000)->pluck($k)->all();
            }
            if (! empty($opsi['ri'])) {
                $hasil['ri'] = DB::table('drm_master_baris')->orderBy('ri')->get(['ri', 'aktivitas', 'bahaya', 'aktif'])
                    ->map(fn ($b) => [(int) $b->ri, mb_strimwidth($b->aktivitas, 0, 60, '…').' · '.mb_strimwidth($b->bahaya, 0, 90, '…').($b->aktif ? '' : ' (nonaktif)')])->all();
            }
            if (! empty($opsi['isiAid'])) {
                $hasil['aid'] = DB::table('drm_master_aktivitas')->pluck('aid', 'aktivitas')->map(fn ($v) => (int) $v)->all();
            }
        }

        return $hasil;
    }

    private function bolehUbah(Request $request): bool
    {
        $editor = (array) config('peta_risiko_drm.master.editor', []);
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        return $editor === [] || in_array((string) $user->getAuthIdentifier(), $editor, true) || in_array((string) ($user->email ?? ''), $editor, true);
    }
}
