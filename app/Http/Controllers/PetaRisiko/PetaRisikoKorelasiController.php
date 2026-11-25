<?php

declare(strict_types=1);

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\DrmKorelasi;
use App\Services\PetaRisiko\Drm\Master\DrmAlurEdit;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use App\Support\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Alur baseline DRM 2.1 (/peta-risiko-pja/korelasi): aktivitas → sub aktivitas → bahaya-risiko → label SAP →
 * grup lokasi → detail lokasi → PJA, dalam mode fokus. Editor master bisa mengubah master lewat modal per simpul.
 */
final class PetaRisikoKorelasiController extends Controller
{
    public function __construct(private readonly DrmKorelasi $korelasi, private readonly DrmAlurEdit $edit)
    {
    }

    public function index(Request $request): View
    {
        $siap = DrmMasterDb::terisi();
        $jenis = (string) $request->query('jenis', '');
        $id = (string) $request->query('id', '');
        $awal = $siap && in_array($jenis, DrmKorelasi::JENIS, true) && $id !== '' ? ['jenis' => $jenis, 'id' => $id] : ($siap ? $this->korelasi->pusatBawaan() : null);

        return view('peta-risiko-pja.drm.korelasi', ['siap' => $siap, 'awal' => $awal, 'bolehUbah' => $this->bolehUbah($request)]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        $v = $request->validate(['jenis' => ['required', 'in:'.implode(',', DrmKorelasi::JENIS)], 'id' => ['required', 'string', 'max:600']]);
        try {
            return response()->json($this->korelasi->alur($v['jenis'], $v['id']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404);
        }
    }

    /** Header unduhan korelasi, dipakai di newKorelasiSpreadsheet() dan unduh(). */
    private const HEADER_UNDUH = [
        'ID baris', 'Aktivitas HIRA', 'Kelompok aktivitas', 'Sub aktivitas', 'Bahaya - risiko', 'Risiko awal',
        'Jenis label', 'Label SAP', 'Grup lokasi', 'Detail lokasi', 'PJA',
    ];

    /**
     * Unduh Excel flat (1 baris = 1 label / 1 jalur lokasi, tidak ada sel digabung — lihat DrmKorelasi::eksporSemua),
     * SEMUA aktivitas, bukan cuma fokus yang sedang dibuka di diagram.
     *
     * Sengaja TIDAK memakai SpreadsheetExporter::createSheetWithHeaders() (menyalakan setAutoSize per kolom —
     * pada ekspor sebesar ini PhpSpreadsheet mengukur ulang lebar TIAP sel dan bisa timeout, lihat keluhan user
     * 25 Nov 2026; lebar kolom di sini dipatok manual, pola yang sama dengan RoadSummaryController::newExportSpreadsheet).
     * Ditulis per potongan 2000 baris (fromArray banyak sekaligus jauh lebih murah daripada satu per baris).
     */
    public function unduh(): Response
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        set_time_limit(0);
        $this->raiseMemoryLimitTo(512);
        $baris = $this->korelasi->eksporSemua();
        $spreadsheet = $this->newKorelasiSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $baris = array_map(fn ($b) => [$b['ri'], $b['aktivitas'], $b['kelompok'], $b['sub'], $b['bahaya'], $b['kb'],
            $b['jenis_label'], $b['label'], $b['grup'], $b['dl'], $b['pja']], $baris);
        foreach (array_chunk($baris, 2000) as $i => $potong) {
            $sheet->fromArray($potong, null, 'A'.(2 + $i * 2000));
        }

        SpreadsheetExporter::download($spreadsheet, 'peta-risiko-korelasi-semua-'.now()->format('Ymd-His').'.xlsx');
    }

    private function newKorelasiSpreadsheet(): Spreadsheet
    {
        $lebar = [10, 28, 18, 24, 42, 13, 16, 36, 24, 32, 24];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADER_UNDUH, null, 'A1');
        $lastCol = Coordinate::stringFromColumnIndex(count(self::HEADER_UNDUH));
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        foreach ($lebar as $i => $w) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($w);
        }
        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    /** Naikkan memory_limit ke $megabytes bila saat ini lebih rendah (tidak pernah menurunkan). */
    private function raiseMemoryLimitTo(int $megabytes): void
    {
        $current = trim((string) ini_get('memory_limit'));
        if ($current === '-1') {
            return;
        }
        $unit = strtolower(substr($current, -1));
        $value = (int) $current;
        $currentMb = match ($unit) {
            'g' => $value * 1024,
            'm' => $value,
            'k' => intdiv($value, 1024),
            default => intdiv($value, 1048576),
        };
        if ($currentMb < $megabytes) {
            ini_set('memory_limit', $megabytes.'M');
        }
    }

    public function cari(Request $request): JsonResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        $v = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return response()->json(['hasil' => $this->korelasi->cari($v['q'])]);
    }

    /** Isi modal edit satu simpul (nilai sekarang + pilihan). */
    public function simpul(Request $request): JsonResponse
    {
        abort_unless(DrmMasterDb::terisi() && $this->bolehUbah($request), 403);
        $v = $request->validate(['jenis' => ['required', 'in:aktivitas,sub,bahaya,dl,obs,oak,ket'], 'id' => ['required', 'string', 'max:600']]);
        try {
            return response()->json(['data' => $this->edit->simpul($v['jenis'], $v['id']), 'opsi' => $this->edit->opsi()]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404);
        }
    }

    /** Simpan satu perubahan dari modal. */
    public function simpan(Request $request): JsonResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
        $v = $request->validate([
            'aksi' => ['required', 'in:aktivitas,sub,bahaya,label,labelganti,labelhapus,grup,dl,pja'],
            'id' => ['required', 'string', 'max:600'],
            'nama' => ['nullable', 'string', 'max:500'],
            'kelompok' => ['nullable', 'string', 'max:10'],
            'bahaya' => ['nullable', 'string', 'max:1000'],
            'kb' => ['nullable', 'string', 'max:20'],
            'aktif' => ['nullable', 'boolean'],
            'jenis' => ['nullable', 'in:OBS,OAK,KET'],
            'teks' => ['nullable', 'string', 'max:500'],
            'gid' => ['nullable', 'integer'],
            'tambah' => ['nullable', 'boolean'],
        ]);
        $id = $v['id'];
        $tambah = (bool) ($v['tambah'] ?? false);
        try {
            $pesan = match ($v['aksi']) {
                'aktivitas' => $this->edit->ubahAktivitas((int) $id, (string) ($v['nama'] ?? ''), (string) ($v['kelompok'] ?? '')),
                'sub' => $this->edit->ubahSub($id, (string) ($v['nama'] ?? '')),
                'bahaya' => $this->edit->ubahBahaya((int) $id, (string) ($v['bahaya'] ?? ''), (string) ($v['kb'] ?? ''), (bool) ($v['aktif'] ?? false)),
                'label' => $this->edit->label((int) $id, (string) ($v['jenis'] ?? ''), (string) ($v['teks'] ?? ''), $tambah),
                // id = teks label lama (simpul label di alur).
                'labelganti' => $this->edit->gantiLabel((string) ($v['jenis'] ?? ''), $id, (string) ($v['teks'] ?? '')),
                'labelhapus' => $this->edit->hapusLabelSemua((string) ($v['jenis'] ?? ''), $id),
                'grup' => $this->edit->grupBaris((int) $id, (int) ($v['gid'] ?? 0), $tambah),
                'dl' => $this->edit->ubahDl((int) $id, (int) ($v['gid'] ?? 0), (bool) ($v['aktif'] ?? false)),
                'pja' => $this->pja($id, (string) ($v['teks'] ?? ''), $tambah),
            };
        } catch (InvalidArgumentException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json(['pesan' => $pesan.' Berlaku setelah peta-risiko:sync berikutnya.']);
    }

    /** PJA per lokasi; id = id detail lokasi (site + lokasi diambil dari master). */
    private function pja(string $idDl, string $nama, bool $tambah): string
    {
        $d = $this->edit->simpul('dl', $idDl);

        return $this->edit->pjaLokasiUbah($d['site'], $d['lokasi'], $nama, $tambah);
    }

    private function bolehUbah(Request $request): bool
    {
        $editor = (array) config('peta_risiko_drm.master.editor', []);
        $user = $request->user();

        return $user !== null && ($editor === [] || in_array((string) $user->getAuthIdentifier(), $editor, true)
            || in_array((string) ($user->email ?? ''), $editor, true));
    }
}
