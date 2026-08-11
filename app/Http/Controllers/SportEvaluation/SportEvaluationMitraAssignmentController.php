<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\SportEvaluation\SportEvaluationMitraAssignmentStoreRequest;
use App\Http\Requests\SportEvaluation\SportEvaluationMitraAssignmentUpdateRequest;
use App\Services\SportEvaluation\SportEvaluationAccessService;
use App\Services\SportEvaluation\SportEvaluationMitraAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

/**
 * CRUD mapping user → site + perusahaan untuk dashboard Mitra Kerja.
 */
final class SportEvaluationMitraAssignmentController extends Controller
{
    public function __construct(
        private readonly SportEvaluationMitraAssignmentService $service,
        private readonly SportEvaluationAccessService $accessService,
    ) {}

    public function index(): View
    {
        $this->authorizeManager();

        return view('evaluasi-well.mitra-assignments.index', [
            'assignments' => $this->service->listAssignments(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeManager();
        $options = $this->service->filterOptions();

        return view('evaluasi-well.mitra-assignments.create', [
            'assignment' => null,
            'userOptions' => $this->service->userOptions(),
            'siteOptions' => $options['sites'],
            'companyOptions' => $options['companies'],
        ]);
    }

    public function store(SportEvaluationMitraAssignmentStoreRequest $request): RedirectResponse
    {
        $this->authorizeManager();

        try {
            $this->service->create($request->validated());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => 'Gagal menyimpan assignment mitra.']);
        }

        return redirect()
            ->route('evaluasi-well.mitra-assignments.index')
            ->with('success', 'Assignment mitra berhasil ditambahkan.');
    }

    public function edit(int $id): View|RedirectResponse
    {
        $this->authorizeManager();

        $assignment = $this->service->find($id);
        if ($assignment === null) {
            return redirect()
                ->route('evaluasi-well.mitra-assignments.index')
                ->withErrors(['form' => 'Assignment tidak ditemukan.']);
        }

        $options = $this->service->filterOptions();

        return view('evaluasi-well.mitra-assignments.edit', [
            'assignment' => $assignment,
            'userOptions' => $this->service->userOptions(),
            'siteOptions' => $options['sites'],
            'companyOptions' => $options['companies'],
        ]);
    }

    public function update(SportEvaluationMitraAssignmentUpdateRequest $request, int $id): RedirectResponse
    {
        $this->authorizeManager();

        $assignment = $this->service->find($id);
        if ($assignment === null) {
            return redirect()
                ->route('evaluasi-well.mitra-assignments.index')
                ->withErrors(['form' => 'Assignment tidak ditemukan.']);
        }

        try {
            $this->service->update($assignment, $request->validated());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['form' => 'Gagal memperbarui assignment mitra.']);
        }

        return redirect()
            ->route('evaluasi-well.mitra-assignments.index')
            ->with('success', 'Assignment mitra berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorizeManager();

        $assignment = $this->service->find($id);
        if ($assignment === null) {
            return redirect()
                ->route('evaluasi-well.mitra-assignments.index')
                ->withErrors(['form' => 'Assignment tidak ditemukan.']);
        }

        try {
            $this->service->delete($assignment);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('evaluasi-well.mitra-assignments.index')
                ->withErrors(['form' => 'Gagal menghapus assignment mitra.']);
        }

        return redirect()
            ->route('evaluasi-well.mitra-assignments.index')
            ->with('success', 'Assignment mitra berhasil dihapus.');
    }

    private function authorizeManager(): void
    {
        if (! $this->accessService->canManageAssignments(auth()->user())) {
            abort(403, 'Anda tidak memiliki akses mengelola assignment Mitra Kerja.');
        }
    }
}
