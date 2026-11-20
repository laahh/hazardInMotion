<?php

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\DrmHiraSumber;
use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\Master\DrmMasterBarisExcel;
use App\Services\PetaRisiko\Drm\Master\DrmMasterBarisUnggah;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Master per baris (acuan utama, keputusan user 18 Nov 2026): tiap baris HIRA (aktivitas · sub · bahaya-risiko) dengan
 * tiga daftar label yang bisa diubah — Item Sumber Observasi, Item Sumber OAK ("aktivitas / sub aktivitas"),
 * Item Sumber Ketidaksesuaian ("ketidaksesuaian / sub"). Yang dihitung sync persis label ini.
 *
 * Mode kolom (master satu tabel, setelah --isi-label-baris): label disimpan di kolom item_sumber_* drm_master_baris.
 * Mode lama: label di drm_master_teks (OBS/OAK) dan drm_master_label (Ketidaksesuaian).
 */
final class PetaRisikoMasterBarisController extends Controller
{
    private const KUNCI_UNGGAH = 'peta-risiko:unggah-baris:';

    public function __construct(private readonly DrmMasterDb $db)
    {
    }

    public function index(Request $request): View
    {
        $siap = DrmMasterDb::siap();
        $data = ['siap' => $siap, 'terisi' => false, 'bolehUbah' => $this->bolehUbah($request),
            // Untuk diagnosis "belum siap": database yang dibaca halaman ini (bukan kredensial).
            'namaDb' => (string) DB::connection()->getDatabaseName(),
            'diagnosis' => [
                'error' => DrmMasterDb::$errorSiap,
                'koneksi' => (string) config('database.default'),
                'host' => (string) config('database.connections.'.config('database.default').'.host'),
                'folder' => base_path(),
                'server' => (string) gethostname(),
                'configCache' => app()->configurationIsCached(),
            ]];
        if (! $siap || ! DrmMasterDb::terisi()) {
            return view('peta-risiko-pja.drm.master-baris', $data);
        }
        $kolom = DrmMasterDb::labelDiBaris();

        // Semua baris aktif dikirim sekali; cari / saring / urut / halaman dikerjakan DataTables di browser.
        $baris = DB::table('drm_master_baris')->where('aktif', true)
            ->orderBy('aktivitas')->orderBy('sub')->orderBy('ri')->get()->all();
        $label = $this->labelHalaman($baris, $kolom);
        [$hira, $hiraGagal] = $this->hiraAsal($baris);
        $terakhir = $this->terakhirDiubah();
        $rows = array_map(fn ($b) => [
            'ri' => (int) $b->ri,
            'a' => (string) $b->aktivitas,
            's' => (string) ($b->sub ?? ''),
            'b' => (string) $b->bahaya,
            'kb' => (string) ($b->kb ?? ''),
            // Dokumen HIRA asal baris (id_hira_master master) + departemennya, dokumen yang teks bahayanya cocok dulu.
            'h' => $hira[(int) $b->ri] ?? [],
            // Departemen koreksi baseline (kosong = ikut HIRA) dan perubahan terakhir [nama, waktu].
            'dk' => (string) ($b->departemen ?? ''),
            'u' => $terakhir[(int) $b->ri] ?? null,
            'OBS' => $label[(int) $b->ri]['OBS'] ?? [],
            'OAK' => $label[(int) $b->ri]['OAK'] ?? [],
            'KET' => $label[(int) $b->ri]['KET'] ?? [],
        ], $baris);
        $departemen = array_values(array_unique(array_filter(array_merge(...array_map(fn ($r) => [...array_column($r['h'], 'dept'), $r['dk']], $rows) ?: [[]]))));
        sort($departemen);

        // array_merge (bukan +): nilai di sini harus menimpa 'terisi' => false di $data.
        return view('peta-risiko-pja.drm.master-baris', array_merge($data, [
            'terisi' => true,
            'rows' => $rows,
            'departemen' => $departemen,
            'hiraGagal' => $hiraGagal,
        ], $this->saran($kolom)));
    }

    /** Unduh Excel baris master, disaring sama dengan halaman (risiko, label kosong, departemen, kata cari). */
    public function unduh(Request $request, DrmMasterBarisUnggah $unggah, DrmMasterBarisExcel $excel): BinaryFileResponse|RedirectResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        $filter = $request->validate([
            'kb' => ['nullable', 'in:Significant,High,Medium,Low'], 'kosong' => ['nullable', 'in:OBS,OAK,KET,SEMUA'],
            'dep' => ['nullable', 'string', 'max:150'], 'q' => ['nullable', 'string', 'max:200'],
        ]);
        try {
            $baris = DB::table('drm_master_baris')->where('aktif', true)->get(['ri', 'idh', 'bahaya'])->all();
            $rows = $unggah->ekspor($filter, $this->hiraAsal($baris)[0]);
            $path = $excel->tulis($rows, array_values($unggah->grupAktif()));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['unduh' => $e->getMessage()]);
        }

        return response()->download($path, 'master-baris-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
    }

    /** Unggah Excel → pratinjau perubahan (belum disimpan), disimpan sementara 30 menit untuk dikonfirmasi. */
    public function unggah(Request $request, DrmMasterBarisUnggah $unggah, DrmMasterBarisExcel $excel): RedirectResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
        $request->validate(['berkas' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);
        $file = $request->file('berkas');
        try {
            $pratinjau = $unggah->pratinjau($excel->baca($file->getRealPath()));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['berkas' => 'Berkas tidak bisa dibaca: '.$e->getMessage()]);
        }
        $token = Str::random(32);
        Cache::put(self::KUNCI_UNGGAH.$token, ['user' => $request->user()?->getAuthIdentifier(), 'berkas' => mb_substr($file->getClientOriginalName(), 0, 80),
            'dibuat' => now()->toIso8601String(), 'pratinjau' => $pratinjau], now()->addMinutes(30));

        return redirect()->route('peta-risiko-pja.master.baris.pratinjau', $token);
    }

    public function pratinjau(Request $request, string $token): View
    {
        $data = $this->ambilUnggahan($request, $token);

        return view('peta-risiko-pja.drm.master-baris-unggah', [...$data, 'token' => $token, 'bolehUbah' => $this->bolehUbah($request)]);
    }

    /** Terapkan unggahan yang sudah dipratinjau: satu transaksi, setiap baris dicatat di riwayat. */
    public function terapkan(Request $request, string $token, DrmMasterBarisUnggah $unggah): RedirectResponse
    {
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
        $data = $this->ambilUnggahan($request, $token);
        $hasil = $unggah->terapkan($data['pratinjau'], $data['berkas']);
        Cache::forget(self::KUNCI_UNGGAH.$token);
        $pesan = $hasil['diubah'].' baris diperbarui dari '.$data['berkas'].'.'
            .($hasil['dilewati'] ? ' '.count($hasil['dilewati']).' baris dilewati karena berubah sejak pratinjau (ID '.implode(', ', $hasil['dilewati']).').' : '')
            .' Berlaku setelah php artisan peta-risiko:sync berikutnya.';

        return redirect()->route('peta-risiko-pja.master.baris.index')->with('pesan', $pesan);
    }

    /** @return array<string, mixed> unggahan milik pengguna ini (404 bila kedaluwarsa / milik orang lain) */
    private function ambilUnggahan(Request $request, string $token): array
    {
        $data = Cache::get(self::KUNCI_UNGGAH.$token);
        abort_if($data === null, 404, 'Pratinjau unggahan sudah kedaluwarsa (30 menit). Unggah ulang berkasnya.');
        abort_unless((string) $data['user'] === (string) $request->user()?->getAuthIdentifier(), 403);

        return $data;
    }

    /**
     * Perubahan terakhir per baris dari riwayat (tabel drm_master_baris, kunci = ri).
     *
     * @return array<int, array{0: string, 1: string}>  ri → [nama pengguna, waktu]
     */
    private function terakhirDiubah(): array
    {
        $id = DB::table('drm_master_log')->where('tabel', 'drm_master_baris')->groupBy('kunci')->selectRaw('max(id) AS id');
        $hasil = [];
        foreach (DB::table('drm_master_log')->whereIn('id', $id)->get(['kunci', 'user_nama', 'dibuat']) as $l) {
            // Kunci non-angka = catatan ringkasan unggahan ("berkas: …"), bukan satu baris.
            if (ctype_digit((string) $l->kunci)) {
                $hasil[(int) $l->kunci] = [(string) ($l->user_nama ?? ''), \Carbon\Carbon::parse($l->dibuat)->translatedFormat('d M Y H:i')];
            }
        }

        return $hasil;
    }

    /**
     * Departemen asal tiap baris dari dokumen HIRA-nya (obds.mv_hira_master, cadangan tabel mentah hira_master).
     * ClickHouse tidak terjangkau → halaman tetap tampil tanpa kolom departemen terisi.
     *
     * @param  list<object>  $baris
     * @return array{0: array<int, list<array{idh: int, rev: int, dept: string, aktif: bool, cocok: bool, ada: bool, kosong: bool}>>, 1: bool}
     */
    private function hiraAsal(array $baris): array
    {
        try {
            $hasil = app(DrmHiraSumber::class)->untukBaris($baris);
        } catch (\Throwable $e) {
            report($e);

            return [[], true];
        }

        return [array_map(fn ($daftar) => array_map(fn ($d) => [
            'idh' => (int) $d['idh'], 'rev' => (int) ($d['rev'] ?? 0), 'dept' => (string) ($d['dept'] ?? ''), 'aktif' => (bool) ($d['aktif'] ?? false),
            'cocok' => (bool) $d['cocok'], 'ada' => (bool) $d['ada'], 'kosong' => (bool) ($d['kosong'] ?? false),
        ], $daftar), $hasil), false];
    }

    /** Tambah satu label ke baris: jenis OBS / OAK (teks) atau KET (ketidaksesuaian + subketidaksesuaian). */
    public function tambah(Request $request, int $ri): RedirectResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
        $baris = DB::table('drm_master_baris')->where('ri', $ri)->first() ?? abort(404);
        $v = $request->validate([
            'jenis' => ['required', 'in:OBS,OAK,KET'],
            'teks' => ['required_unless:jenis,KET', 'nullable', 'string', 'max:500'],
            'k' => ['required_if:jenis,KET', 'nullable', 'string', 'max:300'],
            'sk' => ['required_if:jenis,KET', 'nullable', 'string', 'max:300'],
        ]);
        $label = $v['jenis'] === 'KET' ? trim((string) $v['k']).' / '.trim((string) $v['sk']) : trim((string) $v['teks']);

        if (DrmMasterDb::labelDiBaris()) {
            $kolom = DrmMasterDb::KOLOM_LABEL[$v['jenis']];
            $ada = DrmMasterDb::pisahLabel($baris->{$kolom});
            if (in_array($this->kunci($v['jenis'], $label), array_map(fn ($l) => $this->kunci($v['jenis'], $l), $ada), true)) {
                return back()->with('pesan', 'Label sudah ada di baris ini.');
            }
            $baru = implode("\n", [...$ada, $label]);
            DB::table('drm_master_baris')->where('ri', $ri)->update([$kolom => $baru, 'diubah_oleh' => $request->user()?->getAuthIdentifier(), 'updated_at' => now()]);
            $this->db->catat('drm_master_baris', (string) $ri, 'ubah', [$kolom => $baris->{$kolom}], [$kolom => $baru]);

            return back()->with('pesan', 'Label ditambahkan ke baris '.$ri.'.');
        }

        return $this->tambahTabelLama($request, $ri, $v, $label);
    }

    /** Hapus satu label dari baris. Mode kolom: jenis OBS / OAK / KET + teks label; mode lama: tabel teks / label + id. */
    public function hapus(Request $request, string $jenis, int $id): RedirectResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');

        if (in_array($jenis, ['OBS', 'OAK', 'KET'], true)) {
            $baris = DB::table('drm_master_baris')->where('ri', $id)->first() ?? abort(404);
            $kolom = DrmMasterDb::KOLOM_LABEL[$jenis];
            $hapus = $this->kunci($jenis, trim((string) $request->input('label', '')));
            $ada = DrmMasterDb::pisahLabel($baris->{$kolom});
            $sisa = array_values(array_filter($ada, fn ($l) => $this->kunci($jenis, $l) !== $hapus));
            if (count($sisa) === count($ada)) {
                return back()->with('pesan', 'Label tidak ditemukan di baris ini.');
            }
            DB::table('drm_master_baris')->where('ri', $id)->update([$kolom => implode("\n", $sisa), 'diubah_oleh' => $request->user()?->getAuthIdentifier(), 'updated_at' => now()]);
            $this->db->catat('drm_master_baris', (string) $id, 'ubah', [$kolom => $baris->{$kolom}], [$kolom => implode("\n", $sisa)]);

            return back()->with('pesan', 'Label dihapus dari baris '.$id.'.');
        }

        $tabel = ['teks' => 'drm_master_teks', 'label' => 'drm_master_label'][$jenis] ?? abort(404);
        $lama = DB::table($tabel)->where('id', $id)->first() ?? abort(404);
        DB::table($tabel)->where('id', $id)->delete();
        $this->db->catat($tabel, (string) $id, 'hapus', (array) $lama, null);

        return back()->with('pesan', 'Label dihapus dari baris '.$lama->ri.'.');
    }

    /** Kunci pencocokan label (sama dengan sync): OBS/OAK = DrmMaster::normTeks, Ketidaksesuaian = norm "k / sk". */
    private function kunci(string $jenis, string $label): string
    {
        if ($jenis === 'KET') {
            [$k, $sk] = DrmMasterDb::pisahKet($label);

            return DrmMaster::norm($k).' / '.DrmMaster::norm($sk);
        }

        return DrmMaster::normTeks($label);
    }

    /**
     * Label per baris: [ri][jenis] = list<array{label: string, sumber: string, hapus: array}>.
     *
     * @param  list<object>  $items
     * @return array<int, array<string, list<array<string, mixed>>>>
     */
    private function labelHalaman(array $items, bool $kolom): array
    {
        $hasil = [];
        if ($kolom) {
            foreach ($items as $b) {
                foreach (DrmMasterDb::KOLOM_LABEL as $jenis => $k) {
                    foreach (DrmMasterDb::pisahLabel($b->{$k} ?? '') as $l) {
                        $hasil[(int) $b->ri][$jenis][] = ['label' => $l, 'sumber' => '', 'hapus' => ['jenis' => $jenis, 'id' => (int) $b->ri]];
                    }
                }
            }

            return $hasil;
        }
        $ri = array_map(fn ($b) => (int) $b->ri, $items);
        $adaSumber = Schema::hasColumn('drm_master_teks', 'sumber');
        foreach (DB::table('drm_master_teks')->where('aktif', true)->whereIn('ri', $ri)->orderBy('teks')->get() as $t) {
            $hasil[(int) $t->ri][(string) $t->jenis][] = ['label' => (string) $t->teks, 'sumber' => $adaSumber ? (string) ($t->sumber ?? '') : '',
                'hapus' => ['jenis' => 'teks', 'id' => (int) $t->id]];
        }
        foreach (DB::table('drm_master_label')->where('aktif', true)->whereIn('ri', $ri)->orderBy('ketidaksesuaian')->orderBy('subketidaksesuaian')->get() as $l) {
            $hasil[(int) $l->ri]['KET'][] = ['label' => $l->ketidaksesuaian.' / '.$l->subketidaksesuaian, 'sumber' => (string) ($l->sumber ?? ''),
                'hapus' => ['jenis' => 'label', 'id' => (int) $l->id]];
        }

        return $hasil;
    }

    /** Saran isian (datalist) dari label yang sudah ada. @return array<string, list<string>> */
    private function saran(bool $kolom): array
    {
        if ($kolom) {
            $kumpul = function (string $k): array {
                $s = [];
                foreach (DB::table('drm_master_baris')->whereNotNull($k)->where($k, '!=', '')->pluck($k) as $isi) {
                    foreach (DrmMasterDb::pisahLabel($isi) as $l) {
                        $s[$l] = true;
                    }
                }
                $s = array_keys($s);
                sort($s);

                return array_slice($s, 0, 3000);
            };
            $ket = $kumpul(DrmMasterDb::KOLOM_LABEL['KET']);

            return ['saranObs' => $kumpul('item_sumber_observasi'), 'saranOak' => $kumpul('item_sumber_oak'),
                'saranK' => array_values(array_unique(array_map(fn ($l) => DrmMasterDb::pisahKet($l)[0], $ket))),
                'saranSk' => array_values(array_unique(array_map(fn ($l) => DrmMasterDb::pisahKet($l)[1], $ket)))];
        }

        return [
            'saranObs' => DB::table('drm_master_teks')->where('jenis', 'OBS')->distinct()->orderBy('teks')->limit(3000)->pluck('teks')->all(),
            'saranOak' => DB::table('drm_master_teks')->where('jenis', 'OAK')->distinct()->orderBy('teks')->limit(3000)->pluck('teks')->all(),
            'saranK' => DB::table('drm_master_label')->distinct()->orderBy('ketidaksesuaian')->limit(1000)->pluck('ketidaksesuaian')->all(),
            'saranSk' => DB::table('drm_master_label')->distinct()->orderBy('subketidaksesuaian')->limit(3000)->pluck('subketidaksesuaian')->all(),
        ];
    }

    /** Mode lama: tambah ke drm_master_teks / drm_master_label. */
    private function tambahTabelLama(Request $request, int $ri, array $v, string $label): RedirectResponse
    {
        $sekarang = now();
        if ($v['jenis'] === 'KET') {
            [$k, $sk] = DrmMasterDb::pisahKet($label);
            $sama = DB::table('drm_master_label')->where('ri', $ri)->get()
                ->first(fn ($l) => $this->kunci('KET', $l->ketidaksesuaian.' / '.$l->subketidaksesuaian) === $this->kunci('KET', $label));
            if ($sama !== null) {
                return back()->with('pesan', 'Label sudah ada di baris ini.');
            }
            $nilai = ['ketidaksesuaian' => $k, 'subketidaksesuaian' => $sk, 'ri' => $ri, 'sumber' => 'manual', 'aktif' => true,
                'diubah_oleh' => $request->user()?->getAuthIdentifier(), 'created_at' => $sekarang, 'updated_at' => $sekarang];
            $id = DB::table('drm_master_label')->insertGetId($nilai);
            $this->db->catat('drm_master_label', (string) $id, 'tambah', null, $nilai);

            return back()->with('pesan', 'Label ketidaksesuaian ditambahkan ke baris '.$ri.'.');
        }
        $sama = DB::table('drm_master_teks')->where('ri', $ri)->where('jenis', $v['jenis'])->get()
            ->first(fn ($t) => DrmMaster::crcTeks($t->teks) === DrmMaster::crcTeks($label));
        if ($sama !== null) {
            return back()->with('pesan', 'Label sudah ada di baris ini.');
        }
        $nilai = ['jenis' => $v['jenis'], 'teks' => $label, 'ri' => $ri, 'aktif' => true,
            'diubah_oleh' => $request->user()?->getAuthIdentifier(), 'created_at' => $sekarang, 'updated_at' => $sekarang]
            + (Schema::hasColumn('drm_master_teks', 'sumber') ? ['sumber' => 'manual'] : []);
        $id = DB::table('drm_master_teks')->insertGetId($nilai);
        $this->db->catat('drm_master_teks', (string) $id, 'tambah', null, $nilai);

        return back()->with('pesan', 'Label '.$v['jenis'].' ditambahkan ke baris '.$ri.'.');
    }

    private function bolehUbah(Request $request): bool
    {
        $editor = (array) config('peta_risiko_drm.master.editor', []);
        $user = $request->user();

        return $user !== null && ($editor === [] || in_array((string) $user->getAuthIdentifier(), $editor, true)
            || in_array((string) ($user->email ?? ''), $editor, true));
    }
}
