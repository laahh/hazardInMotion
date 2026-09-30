<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringSafetyEngineering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MonitoringSafetyEngineering\Concerns\ProvidesMonitoringSafetyEngineeringLayout;
use App\Http\Requests\MonitoringSafetyEngineering\MonitoringSafetyEngineeringPicAssignmentRequest;
use App\Models\User;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringPicAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Role akses Monitoring Safety Engineering: assign orang (nama / SID) ke perusahaan & site.
 */
final class MonitoringSafetyEngineeringPicAssignmentController extends Controller
{
    use ProvidesMonitoringSafetyEngineeringLayout;

    public function __construct(
        private readonly MonitoringSafetyEngineeringPicAssignmentService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeManager();

        $search = trim((string) $request->query('q', ''));

        return view('MonitoringSafetyEngginering.role-access.index', $this->monitoringSafetyEngineeringViewData('role-access', [
            'assignments' => $this->service->listAssignments($search),
            'search' => $search,
            'tablesReady' => $this->service->tablesReady(),
        ]));
    }

    public function create(): View|RedirectResponse
    {
        $this->authorizeManager();

        if (! $this->service->tablesReady()) {
            return $this->missingTableRedirect();
        }

        return view('MonitoringSafetyEngginering.role-access.create', $this->formViewData(null));
    }

    public function store(MonitoringSafetyEngineeringPicAssignmentRequest $request): RedirectResponse
    {
        $this->authorizeManager();

        if (! $this->service->tablesReady()) {
            return $this->missingTableRedirect();
        }

        try {
            $assignment = $this->service->create($request->validated());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['form' => 'Gagal menyimpan assignment role akses.']);
        }

        return redirect()
            ->route('monitoring-safety-engineering.role-access.index')
            ->with('success', 'Role akses untuk ' . $assignment->identityLabel() . ' berhasil ditambahkan.');
    }

    public function edit(int $id): View|RedirectResponse
    {
        $this->authorizeManager();

        if (! $this->service->tablesReady()) {
            return $this->missingTableRedirect();
        }

        $assignment = $this->service->find($id);
        if ($assignment === null) {
            return $this->notFoundRedirect();
        }

        return view('MonitoringSafetyEngginering.role-access.edit', $this->formViewData($assignment));
    }

    public function update(MonitoringSafetyEngineeringPicAssignmentRequest $request, int $id): RedirectResponse
    {
        $this->authorizeManager();

        if (! $this->service->tablesReady()) {
            return $this->missingTableRedirect();
        }

        $assignment = $this->service->find($id);
        if ($assignment === null) {
            return $this->notFoundRedirect();
        }

        try {
            $this->service->update($assignment, $request->validated());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['form' => 'Gagal memperbarui assignment role akses.']);
        }

        return redirect()
            ->route('monitoring-safety-engineering.role-access.index')
            ->with('success', 'Role akses untuk ' . $assignment->identityLabel() . ' berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorizeManager();

        if (! $this->service->tablesReady()) {
            return $this->missingTableRedirect();
        }

        $assignment = $this->service->find($id);
        if ($assignment === null) {
            return $this->notFoundRedirect();
        }

        $label = $assignment->identityLabel();

        try {
            $this->service->delete($assignment);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('monitoring-safety-engineering.role-access.index')
                ->withErrors(['form' => 'Gagal menghapus assignment role akses.']);
        }

        return redirect()
            ->route('monitoring-safety-engineering.role-access.index')
            ->with('success', 'Role akses untuk ' . $label . ' berhasil dihapus.');
    }

    /**
     * Impor sekali jalan dari NAMA_PIC.json untuk mengisi data awal.
     */
    public function importLegacy(): RedirectResponse
    {
        $this->authorizeManager();

        if (! $this->service->tablesReady()) {
            return $this->missingTableRedirect();
        }

        try {
            $result = $this->service->importFromLegacyJson();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('monitoring-safety-engineering.role-access.index')
                ->withErrors(['form' => 'Gagal mengimpor data dari NAMA_PIC.json.']);
        }

        return redirect()
            ->route('monitoring-safety-engineering.role-access.index')
            ->with('success', sprintf(
                'Impor selesai: %d PIC baru, %d pasangan perusahaan/site baru, %d baris dilewati.',
                $result['created'],
                $result['scopes_created'],
                $result['skipped'],
            ));
    }

    /**
     * @return array<string, mixed>
     */
    private function formViewData(?object $assignment): array
    {
        $options = $this->service->filterOptions();

        return $this->monitoringSafetyEngineeringViewData('role-access', [
            'assignment' => $assignment,
            'userOptions' => $this->service->userOptions(),
            'siteOptions' => $options['sites'],
            'companyOptions' => $options['companies'],
        ]);
    }

    private function missingTableRedirect(): RedirectResponse
    {
        return redirect()
            ->route('monitoring-safety-engineering.role-access.index')
            ->withErrors(['form' => 'Tabel assignment role akses belum tersedia. Jalankan migration terlebih dahulu.']);
    }

    private function notFoundRedirect(): RedirectResponse
    {
        return redirect()
            ->route('monitoring-safety-engineering.role-access.index')
            ->withErrors(['form' => 'Assignment tidak ditemukan.']);
    }

    private function authorizeManager(): void
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403, 'Anda tidak memiliki akses mengelola role akses Monitoring Safety Engineering.');
        }
    }
}
