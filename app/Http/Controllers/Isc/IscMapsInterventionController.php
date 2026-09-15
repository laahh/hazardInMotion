<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Actions\Isc\IscHazardReportStoreAction;
use App\Actions\Isc\IscInterventionStoreAction;
use App\Actions\Isc\IscSyncActiveViolationsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Isc\IscHazardReportStoreRequest;
use App\Http\Requests\Isc\IscInterventionStoreRequest;
use App\Services\Isc\IscHazardEmployeeLookupService;
use App\Services\Isc\IscHazardLocationLookupService;
use App\Services\Isc\IscHazardPjaLookupService;
use App\Services\Isc\IscHazardSysUserLookupService;
use App\Services\Isc\IscMapsInterventionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class IscMapsInterventionController extends Controller
{
    public function __construct(
        private readonly IscMapsInterventionService $tasks,
        private readonly IscInterventionStoreAction $storeAction,
        private readonly IscHazardReportStoreAction $hazardStoreAction,
        private readonly IscHazardEmployeeLookupService $employees,
        private readonly IscHazardSysUserLookupService $sysUsers,
        private readonly IscHazardLocationLookupService $locations,
        private readonly IscHazardPjaLookupService $pja,
        private readonly IscSyncActiveViolationsAction $syncViolations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $demo = $request->query('source') === 'demo';
        if (! $demo) {
            $this->syncLiveViolationsQuietly();
        }

        return response()->json([
            'success' => true,
            ...$this->tasks->payload($request->user(), $demo),
        ]);
    }

    private function syncLiveViolationsQuietly(): void
    {
        try {
            Cache::remember('isc.maps.sync_active_violations.v1', 60, function (): bool {
                $this->syncViolations->execute(false);

                return true;
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function store(IscInterventionStoreRequest $request): JsonResponse
    {
        $intervention = $this->storeAction->execute($request->user(), $request->validated(), []);

        return response()->json([
            'success' => true,
            'intervention_id' => $intervention->id,
            'event_id' => $intervention->event_id,
            'status' => $intervention->event?->status,
        ]);
    }

    public function storeHazardReport(IscHazardReportStoreRequest $request): JsonResponse
    {
        try {
            $report = $this->hazardStoreAction->execute(
                $request->user(),
                $request->validated(),
                $request->file('foto'),
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan laporan hazard: '.$e->getMessage(),
            ], 500);
        }

        $isDemo = $report->status === 'demo' || ! $report->exists;

        return response()->json([
            'success' => true,
            'demo' => $isDemo,
            'persisted' => ! $isDemo,
            'report_id' => $report->id,
            'event_id' => $report->event_id,
            'intervention_id' => $report->intervention_id,
            'status' => $report->status,
            'foto_path' => $report->foto_path,
            'message' => $isDemo
                ? 'Laporan hazard dummy diterima (Belum disimpan ke DB).'
                : 'Laporan hazard berhasil disimpan ke database.',
        ], 201);
    }

    public function lookupEmployees(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        try {
            $results = $this->employees->search($q);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mencari karyawan: '.$e->getMessage(),
                'results' => [],
            ], 504);
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'source' => 'bcsid.bep_vw_safety_all_karyawan',
        ]);
    }

    /**
     * Lookup akses (username/password) + profil pelapor by SID dari hse_automation.
     */
    public function lookupSysUser(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', $request->query('sid', '')));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'results' => [], 'user' => null]);
        }

        // Prefer exact SID match (case-insensitive).
        $exact = $this->sysUsers->findBySid($q);
        $results = $exact !== null ? [$exact] : $this->sysUsers->search($q);

        return response()->json([
            'success' => true,
            'results' => $results,
            'user' => $exact ?? ($results[0] ?? null),
            'source' => 'bcbeats.bep_vw_karyawan_sysuser_user_role',
        ]);
    }

    public function lookupLokasi(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $site = trim((string) $request->query('site', ''));

        return response()->json([
            'success' => true,
            'data' => $this->locations->lokasiOptions($q, $site),
            'source' => IscHazardLocationLookupService::VIEW,
        ]);
    }

    public function lookupDetailLokasi(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $lokasi = trim((string) $request->query('lokasi', ''));
        $site = trim((string) $request->query('site', ''));

        return response()->json([
            'success' => true,
            'data' => $this->locations->detailLokasiOptions($lokasi, $q, $site),
            'source' => IscHazardLocationLookupService::VIEW,
        ]);
    }

    public function lookupPjaBc(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $lokasi = trim((string) $request->query('lokasi', ''));
        $site = trim((string) $request->query('site', ''));

        return response()->json([
            'success' => true,
            'data' => $this->pja->bcOptions($q, $lokasi, $site),
            'suggest' => $lokasi !== '' ? $this->pja->suggestForLokasi($lokasi, $site) : null,
            'source' => IscHazardPjaLookupService::VIEW,
        ]);
    }

    public function lookupPjaMitra(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $lokasi = trim((string) $request->query('lokasi', ''));
        $site = trim((string) $request->query('site', ''));

        return response()->json([
            'success' => true,
            'data' => $this->pja->mitraOptions($q, $lokasi, $site),
            'suggest' => $lokasi !== '' ? $this->pja->suggestForLokasi($lokasi, $site) : null,
            'source' => IscHazardPjaLookupService::VIEW,
        ]);
    }
}
