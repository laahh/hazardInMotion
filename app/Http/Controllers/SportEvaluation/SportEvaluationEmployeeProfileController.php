<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\SportEvaluation\SportEvaluationEmployeeProfileStoreRequest;
use App\Http\Requests\SportEvaluation\SportEvaluationEmployeeProfileUpdateRequest;
use App\Services\SportEvaluation\SportEvaluationEmployeeProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
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

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->service->datatable($request));
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
}
