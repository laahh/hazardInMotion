<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\SportEvaluation\SportEvaluationEmployeeProfileImportRequest;
use App\Http\Requests\SportEvaluation\SportEvaluationEmployeeProfileStoreRequest;
use App\Http\Requests\SportEvaluation\SportEvaluationEmployeeProfileUpdateRequest;
use App\Jobs\SportEvaluation\SportEvaluationSyncHseEmployeesJob;
use App\Services\SportEvaluation\SportEvaluationEmployeeProfileService;
use App\Support\SpreadsheetExporter;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Manajemen employee_profiles BeWell (create / read / update, tanpa delete).
 */
final class SportEvaluationEmployeeProfileController extends Controller
{
    public function __construct(
        private readonly SportEvaluationEmployeeProfileService $service,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->indexPage($request);

        return view('evaluasi-well.users.index', $data);
    }

    public function create(): View
    {
        return view('evaluasi-well.users.create', [
            'connectionUp' => $this->service->indexPage(request())['connectionUp'],
            'statusOptions' => SportEvaluationEmployeeProfileService::STATUS_OPTIONS,
            'employee' => null,
        ]);
    }

    public function store(SportEvaluationEmployeeProfileStoreRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->validated());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => $e->getMessage()]);
        } catch (QueryException $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => 'Gagal menyimpan karyawan ke BeWell.']);
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => 'Gagal menyimpan karyawan ke BeWell.']);
        }

        return redirect()
            ->route('evaluasi-well.users.index')
            ->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function edit(int $id): View|RedirectResponse
    {
        $employee = $this->service->find($id);
        if ($employee === null) {
            return redirect()
                ->route('evaluasi-well.users.index')
                ->withErrors(['form' => 'Karyawan tidak ditemukan atau koneksi BeWell tidak tersedia.']);
        }

        return view('evaluasi-well.users.edit', [
            'connectionUp' => true,
            'statusOptions' => SportEvaluationEmployeeProfileService::STATUS_OPTIONS,
            'employee' => $employee,
        ]);
    }

    public function update(SportEvaluationEmployeeProfileUpdateRequest $request, int $id): RedirectResponse
    {
        try {
            $this->service->update($id, $request->validated());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => $e->getMessage()]);
        } catch (QueryException $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => 'Gagal memperbarui karyawan di BeWell.']);
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => 'Gagal memperbarui karyawan di BeWell.']);
        }

        return redirect()
            ->route('evaluasi-well.users.index')
            ->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function importForm(): View
    {
        return view('evaluasi-well.users.import', [
            'connectionUp' => $this->service->indexPage(request())['connectionUp'],
        ]);
    }

    public function downloadTemplate(): void
    {
        $headers = $this->service->importTemplateHeaders();
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders($headers);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Karyawan');

        $rowNum = 2;
        foreach ($this->service->importTemplateExampleRows() as $example) {
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col.$rowNum, $value);
                $col++;
            }
            $rowNum++;
        }

        SpreadsheetExporter::download($spreadsheet, 'template_bewell_karyawan_import.xlsx');
    }

    public function import(SportEvaluationEmployeeProfileImportRequest $request): RedirectResponse
    {
        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $result = $this->service->importRows($rows);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('evaluasi-well.users.import-form')
                ->with('error', $e->getMessage());
        } catch (RuntimeException $e) {
            return redirect()
                ->route('evaluasi-well.users.import-form')
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('evaluasi-well.users.import-form')
                ->with('error', 'Gagal membaca/mengimpor file Excel.');
        }

        $message = "Import selesai: {$result['created']} ditambah, {$result['updated']} diupdate.";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} baris dilewati.";
        }

        if ($result['errors'] !== []) {
            $request->session()->flash('import_errors', array_slice($result['errors'], 0, 50));
        }

        return redirect()
            ->route('evaluasi-well.users.index')
            ->with('success', $message);
    }

    public function syncFromHse(): RedirectResponse
    {
        $apiKey = trim((string) config('services.evaluasi_well_hse.api_key', ''));
        $username = trim((string) config('services.evaluasi_well_hse.username', ''));
        $password = (string) config('services.evaluasi_well_hse.password', '');

        if ($apiKey === '' && ($username === '' || $password === '')) {
            return redirect()
                ->route('evaluasi-well.users.index')
                ->withErrors([
                    'form' => 'Kredensial HSE belum diisi di .env. Isi EVALUASI_WELL_HSE_API_KEY atau fallback legacy EVALUASI_WELL_HSE_USERNAME / EVALUASI_WELL_HSE_PASSWORD.',
                ]);
        }

        if (! $this->service->indexPage(request())['connectionUp']) {
            return redirect()
                ->route('evaluasi-well.users.index')
                ->withErrors([
                    'form' => 'Koneksi BeWell tidak tersedia. Aktifkan tunnel sebelum sync.',
                ]);
        }

        SportEvaluationSyncHseEmployeesJob::dispatch();

        return redirect()
            ->route('evaluasi-well.users.index')
            ->with(
                'success',
                'Sync HSE diantrikan. Karyawan baru akan ditambahkan; SID yang sudah ada tidak diubah datanya, kecuali statusnya otomatis jadi NONAKTIF bila sudah tidak ada di roster aktif HSE. Pantau log queue untuk hasil.'
            );
    }
}
