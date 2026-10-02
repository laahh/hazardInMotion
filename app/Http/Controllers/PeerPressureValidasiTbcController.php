<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PeerPressureValidasiTbcRequest;
use App\Jobs\ImportValidasiTbcJob;
use App\Models\ValidasiTbc;
use App\Models\ValidasiTbcImportLog;
use App\Services\PeerPressure\ValidasiTbcImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class PeerPressureValidasiTbcController extends Controller
{
    public function index(Request $request): View
    {
        $query = ValidasiTbc::query()->orderByDesc('id');

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                foreach (ValidasiTbcImportService::COLUMN_IMPORT as $column) {
                    $sub->orWhere($column, 'like', '%' . $q . '%');
                }
            });
        }

        $rows = $query->paginate(10)->withQueryString();

        $importLogs = ValidasiTbcImportLog::query()
            ->latest()
            ->limit(25)
            ->get();

        $hasPendingImport = $importLogs->contains(fn (ValidasiTbcImportLog $l): bool => $l->status === 'pending');

        return view('peer-pressure-edukasi.validasi-tbc.index', [
            'rows' => $rows,
            'q' => $q,
            'navActive' => 'validasi-tbc',
            'importLogs' => $importLogs,
            'hasPendingImport' => $hasPendingImport,
        ]);
    }

    public function create(): View
    {
        return view('peer-pressure-edukasi.validasi-tbc.form', [
            'mode' => 'create',
            'row' => new ValidasiTbc(),
            'navActive' => 'validasi-tbc',
        ]);
    }

    public function store(PeerPressureValidasiTbcRequest $request): RedirectResponse
    {
        ValidasiTbc::query()->create($request->attributesPayload());

        return redirect()
            ->route('peer-pressure-edukasi.validasi-tbc.index')
            ->with('success', 'Data validasi TBC berhasil ditambahkan.');
    }

    public function edit(int $id): View
    {
        $row = ValidasiTbc::query()->findOrFail($id);

        return view('peer-pressure-edukasi.validasi-tbc.form', [
            'mode' => 'edit',
            'row' => $row,
            'navActive' => 'validasi-tbc',
        ]);
    }

    public function update(PeerPressureValidasiTbcRequest $request, int $id): RedirectResponse
    {
        $row = ValidasiTbc::query()->findOrFail($id);
        $row->update($request->attributesPayload());

        return redirect()
            ->route('peer-pressure-edukasi.validasi-tbc.index')
            ->with('success', 'Data validasi TBC berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $row = ValidasiTbc::query()->findOrFail($id);
        $row->delete();

        return redirect()
            ->route('peer-pressure-edukasi.validasi-tbc.index')
            ->with('success', 'Data validasi TBC berhasil dihapus.');
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(ValidasiTbcImportService::SHEET_IMPORT);

        $col = 1;
        foreach (ValidasiTbcImportService::HEADER_IMPORT as $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . '1', $h);
            $col++;
        }
        $lastColLetter = Coordinate::stringFromColumnIndex(count(ValidasiTbcImportService::HEADER_IMPORT));
        $sheet->getStyle('A1:' . $lastColLetter . '1')->getFont()->setBold(true);

        $sample = [
            'TL-001',
            'Hazard contoh',
            'GR',
            'Catatan singkat',
            'Blindspot contoh',
            'PSPP-1',
            'Kategori A',
            'SID001',
            'Nama pelaku/pelanggar',
        ];
        $col = 1;
        foreach ($sample as $v) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . '2', $v);
            $col++;
        }

        foreach (range(1, count(ValidasiTbcImportService::HEADER_IMPORT)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $filename = 'template_validasi_tbc_' . date('Y-m-d') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request, ValidasiTbcImportService $importService): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
        ], [
            'excel_file.required' => 'File Excel wajib diunggah.',
            'excel_file.file' => 'File tidak terkirim dengan benar. Pastikan ukuran maks. 50 MB, format .xlsx/.xls, serta batas PHP upload_max_filesize dan post_max_size (setelah mengubah php.ini, restart Apache/nginx).',
            'excel_file.uploaded' => 'Unggah gagal (sering karena file terlalu besar melebihi batas server). Perkecil file atau naikkan upload_max_filesize dan post_max_size di php.ini.',
            'excel_file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'excel_file.max' => 'Ukuran file maksimal 50 MB.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('peer-pressure-edukasi.validasi-tbc.index', ['modal' => 'import'])
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $file = $request->file('excel_file');
            if ($file === null) {
                return redirect()
                    ->route('peer-pressure-edukasi.validasi-tbc.index', ['modal' => 'import'])
                    ->with('notify_error', 'File tidak valid.');
            }

            $relativePath = $file->store('imports/validasi-tbc', 'local');
            if ($relativePath === false) {
                return redirect()
                    ->route('peer-pressure-edukasi.validasi-tbc.index', ['modal' => 'import'])
                    ->with('notify_error', 'Gagal menyimpan file sementara.');
            }

            $fullPath = storage_path('app/' . $relativePath);
            try {
                $importService->validateHeaderOnly($fullPath);
            } catch (\InvalidArgumentException $e) {
                Storage::disk('local')->delete($relativePath);

                return redirect()
                    ->route('peer-pressure-edukasi.validasi-tbc.index', ['modal' => 'import'])
                    ->with('notify_error', $e->getMessage());
            }

            if (! Storage::disk('local')->exists($relativePath)) {
                return redirect()
                    ->route('peer-pressure-edukasi.validasi-tbc.index', ['modal' => 'import'])
                    ->with('notify_error', 'File tidak ditemukan di penyimpanan setelah unggah. Coba lagi atau cek izin folder storage/app.');
            }

            $log = ValidasiTbcImportLog::query()->create([
                'uuid' => (string) Str::uuid(),
                'status' => 'pending',
            ]);

            /** Setelah response HTTP selesai — hindari race dengan worker / proses lain saat file baru ditulis. */
            ImportValidasiTbcJob::dispatchAfterResponse($relativePath, $log->id);

            $msg =
                'File berhasil diunggah. Import diproses di latar belakang — lihat tabel «Riwayat impor Excel» di halaman ini untuk status berhasil/gagal dan jumlah baris.';
            if (config('queue.default') === 'sync') {
                $msg .=
                    ' Untuk file besar, set QUEUE_CONNECTION=database (atau redis) di file .env, jalankan migrasi jobs jika belum, lalu jalankan: php artisan queue:work — agar permintaan tidak timeout.';
            } else {
                $msg .= ' Pastikan worker antrian berjalan: php artisan queue:work';
            }

            return redirect()
                ->route('peer-pressure-edukasi.validasi-tbc.index')
                ->with('notify_success', $msg);
        } catch (Throwable $e) {
            Log::error('Validasi TBC import enqueue: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()
                ->route('peer-pressure-edukasi.validasi-tbc.index', ['modal' => 'import'])
                ->with('notify_error', 'Gagal mengantrekan import. Coba lagi atau hubungi administrator.');
        }
    }
}
