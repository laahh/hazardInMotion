<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Models\SidRosterBannedMaster;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Master roster karyawan yang di-banned (sid_roster_banned_master).
 * CRUD + import Excel, dipakai dari /pra-operasi.
 */
class SidRosterBannedMasterController extends Controller
{
    public function index(): View
    {
        return view('pra-operasi.roster-banned.index');
    }

    /**
     * DataTables server-side data (JSON).
     */
    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length < 1 || $length > 100) {
            $length = 25;
        }
        $search = trim((string) ($request->input('search.value') ?? ''));
        $orderColIndex = (int) $request->input('order.0.column', 3);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $orderColumns = [
            1 => 'nik',
            2 => 'sid',
            3 => 'nama',
            4 => 'perusahaan',
            5 => 'site_dedicated',
            6 => 'alasan_pelanggaran',
            7 => 'tanggal_pelanggaran',
        ];
        $orderBy = $orderColumns[$orderColIndex] ?? 'nama';

        $query = SidRosterBannedMaster::query();
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('nik', 'like', '%'.$search.'%')
                    ->orWhere('sid', 'like', '%'.$search.'%')
                    ->orWhere('nama', 'like', '%'.$search.'%')
                    ->orWhere('perusahaan', 'like', '%'.$search.'%')
                    ->orWhere('site_dedicated', 'like', '%'.$search.'%')
                    ->orWhere('alasan_pelanggaran', 'like', '%'.$search.'%');
            });
        }

        $recordsTotal = SidRosterBannedMaster::count();
        $recordsFiltered = (clone $query)->count();
        $items = $query->orderBy($orderBy, $orderDir)->skip($start)->take($length)->get();

        $data = [];
        foreach ($items as $idx => $item) {
            $editUrl = route('pra-operasi.roster-banned.edit', $item->id);
            $destroyUrl = route('pra-operasi.roster-banned.destroy', $item->id);
            $csrf = csrf_token();
            $aksi = '<a href="'.e($editUrl).'" class="btn btn-sm btn-outline-primary" title="Edit"><iconify-icon icon="solar:pen-outline"></iconify-icon></a> ';
            $aksi .= '<form action="'.e($destroyUrl).'" method="POST" class="d-inline" onsubmit="return confirm(\'Yakin hapus data ini?\');"><input type="hidden" name="_token" value="'.e($csrf).'"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><iconify-icon icon="solar:trash-bin-trash-outline"></iconify-icon></button></form>';

            $data[] = [
                'DT_RowIndex' => $start + $idx + 1,
                'nik' => $item->nik ?? '-',
                'sid' => $item->sid ?: '-',
                'nama' => $item->nama ?? '-',
                'perusahaan' => $item->perusahaan ?: '-',
                'site_dedicated' => $item->site_dedicated ?: '-',
                'alasan_pelanggaran' => $item->alasan_pelanggaran ?? '-',
                'tanggal_pelanggaran' => $item->tanggal_pelanggaran ? $item->tanggal_pelanggaran->format('d/m/Y') : '-',
                'aksi' => $aksi,
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(): View
    {
        return view('pra-operasi.roster-banned.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        SidRosterBannedMaster::create(array_merge($validated, [
            'created_by' => Auth::user()?->name ?? 'system',
        ]));

        return redirect()
            ->route('pra-operasi.roster-banned.index')
            ->with('success', 'Data berhasil ditambah.');
    }

    public function edit(int $id): View
    {
        $item = SidRosterBannedMaster::findOrFail($id);

        return view('pra-operasi.roster-banned.edit', compact('item'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = SidRosterBannedMaster::findOrFail($id);
        $validated = $this->validatePayload($request);

        $item->update($validated);

        return redirect()
            ->route('pra-operasi.roster-banned.index')
            ->with('success', 'Data berhasil diubah.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $item = SidRosterBannedMaster::findOrFail($id);
        $item->delete();

        return redirect()
            ->route('pra-operasi.roster-banned.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nik' => ['required', 'string', 'max:64'],
            'sid' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:255'],
            'perusahaan' => ['nullable', 'string', 'max:255'],
            'site_dedicated' => ['nullable', 'string', 'max:50'],
            'alasan_pelanggaran' => ['required', 'string', 'max:500'],
            'tanggal_pelanggaran' => ['nullable', 'date'],
        ], [
            'nik.required' => 'NIK wajib diisi.',
            'nama.required' => 'Nama wajib diisi.',
            'alasan_pelanggaran.required' => 'Alasan pelanggaran wajib diisi.',
        ]);
    }

    public function importForm(): View
    {
        return view('pra-operasi.roster-banned.import');
    }

    /**
     * Download template Excel (header + 1 baris contoh).
     */
    public function downloadTemplate()
    {
        $headers = ['NIK', 'SID', 'Nama', 'Perusahaan', 'Site Dedicated', 'Alasan Pelanggaran', 'Tanggal Pelanggaran'];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $col++;
        }
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);

        $sheet->setCellValue('A2', '61091015');
        $sheet->setCellValue('B2', '6H2DF');
        $sheet->setCellValue('C2', 'AGUS CAHYONO');
        $sheet->setCellValue('D2', 'PT Pamapersada Nusantara');
        $sheet->setCellValue('E2', 'GMO');
        $sheet->setCellValue('F2', 'Melanggar SOP RFID saat pre-operasi');
        $sheet->setCellValue('G2', '2026-09-28');

        foreach (range('A', $lastCol) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $filename = 'template_sid_roster_banned_master_'.date('Y-m-d').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Import data dari Excel. Setiap baris selalu jadi record baru (tidak
     * upsert) — riwayat pelanggaran yang sama untuk NIK yang sama tetap
     * dicatat masing-masing sebagai baris terpisah.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [
            'excel_file.required' => 'File Excel wajib diupload.',
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $dataRows = array_slice($rows, 1);

            $createdBy = Auth::user()?->name ?? 'system';
            $imported = 0;
            $errors = [];
            $rowNum = 2;

            DB::beginTransaction();

            foreach ($dataRows as $row) {
                if (array_filter($row) === []) {
                    $rowNum++;

                    continue;
                }

                $nik = isset($row[0]) ? trim((string) $row[0]) : '';
                $sid = isset($row[1]) ? trim((string) $row[1]) : '';
                $nama = isset($row[2]) ? trim((string) $row[2]) : '';
                $perusahaan = isset($row[3]) ? trim((string) $row[3]) : '';
                $site = isset($row[4]) ? trim((string) $row[4]) : '';
                $alasan = isset($row[5]) ? trim((string) $row[5]) : '';
                $tanggalRaw = isset($row[6]) ? trim((string) $row[6]) : '';

                if ($nik === '' || $nama === '' || $alasan === '') {
                    $errors[] = "Baris {$rowNum}: NIK, Nama, dan Alasan Pelanggaran wajib diisi.";
                    $rowNum++;

                    continue;
                }

                $tanggal = null;
                if ($tanggalRaw !== '') {
                    try {
                        $tanggal = is_numeric($tanggalRaw)
                            ? ExcelDate::excelToDateTimeObject((float) $tanggalRaw)->format('Y-m-d')
                            : Carbon::parse($tanggalRaw)->format('Y-m-d');
                    } catch (Exception) {
                        $errors[] = "Baris {$rowNum}: format Tanggal Pelanggaran tidak valid, dilewati.";
                        $rowNum++;

                        continue;
                    }
                }

                SidRosterBannedMaster::create([
                    'nik' => $nik,
                    'sid' => $sid ?: null,
                    'nama' => $nama,
                    'perusahaan' => $perusahaan ?: null,
                    'site_dedicated' => $site ?: null,
                    'alasan_pelanggaran' => $alasan,
                    'tanggal_pelanggaran' => $tanggal,
                    'created_by' => $createdBy,
                ]);
                $imported++;
                $rowNum++;
            }

            DB::commit();

            $message = "Import selesai: {$imported} baris ditambahkan.";
            if ($errors !== []) {
                $message .= ' '.count($errors).' baris dilewati.';
            }

            if ($errors !== []) {
                $request->session()->flash('import_errors', array_slice($errors, 0, 50));
            }

            return redirect()
                ->route('pra-operasi.roster-banned.index')
                ->with('success', $message);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('SidRosterBannedMaster import: '.$e->getMessage());

            return redirect()
                ->route('pra-operasi.roster-banned.import-form')
                ->with('error', 'Gagal import: '.$e->getMessage());
        }
    }
}
