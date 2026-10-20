<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedUnbanStatus;
use App\Models\AutoBannedStatusSnapshot;
use App\Models\AutoBannedUnbanRequest;
use App\Models\User;
use App\Support\AutoBanned\AutoBannedSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AutoBannedSodVerificationService
{
    public function __construct(
        private readonly AutoBannedStatusNormalizer $normalizer,
        private readonly AutoBannedStatusResolver $statusResolver,
    ) {}

    public function tableAvailable(): bool
    {
        return AutoBannedSchema::hasUnbanRequestsTable();
    }

    /** Batas aman tab "Semua" (seluruh periode & status, diolah DataTables di browser). */
    public const BATAS_SEMUA = 10000;

    /**
     * Seluruh pengajuan lintas periode & status untuk tab "Semua". Tidak
     * memakai filter minggu/site/perusahaan dari server — penyaringan
     * dikerjakan DataTables di browser, termasuk ekspor Excel-nya.
     *
     * @return Collection<int, AutoBannedUnbanRequest>
     */
    public function listAllRequests(): Collection
    {
        if (! $this->tableAvailable()) {
            return collect();
        }

        return AutoBannedUnbanRequest::query()
            ->orderByDesc('created_at')
            ->limit(self::BATAS_SEMUA)
            ->get();
    }

    /**
     * Ringkasan kartu untuk tab "Semua": seluruh data tanpa filter periode.
     *
     * @return array{pending: int, approved: int, rejected: int, total: int, withEvidence: int}
     */
    public function summaryCountsAll(): array
    {
        return $this->summaryCounts([]);
    }

    /**
     * @param  array{site?: string, week?: string, year?: string, perusahaan?: string, q?: string, status?: string}  $filters
     * @return Collection<int, AutoBannedUnbanRequest>
     */
    public function listSubmittedRequests(array $filters): Collection
    {
        if (! $this->tableAvailable()) {
            return collect();
        }

        $query = AutoBannedUnbanRequest::query()
            ->orderByDesc('created_at');

        $this->applyFilters($query, $filters);

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all' && in_array($status, [
            AutoBannedUnbanStatus::Pending->value,
            AutoBannedUnbanStatus::Approved->value,
            AutoBannedUnbanStatus::Rejected->value,
        ], true)) {
            $query->where('status', $status);
        }

        return $query->limit(300)->get();
    }

    /**
     * @param  array{site?: string, week?: string, year?: string, perusahaan?: string, q?: string, status?: string}  $filters
     * @return array{pending: int, approved: int, rejected: int, total: int, withEvidence: int}
     */
    public function summaryCounts(array $filters): array
    {
        if (! $this->tableAvailable()) {
            return [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'total' => 0,
                'withEvidence' => 0,
            ];
        }

        $base = AutoBannedUnbanRequest::query();
        $this->applyFilters($base, $filters);

        $rows = (clone $base)->select(['status', 'evidence_file_path'])->get();

        return [
            'pending' => $rows->where('status', AutoBannedUnbanStatus::Pending)->count(),
            'approved' => $rows->where('status', AutoBannedUnbanStatus::Approved)->count(),
            'rejected' => $rows->where('status', AutoBannedUnbanStatus::Rejected)->count(),
            'total' => $rows->count(),
            'withEvidence' => $rows->filter(fn (AutoBannedUnbanRequest $row): bool => trim((string) ($row->evidence_file_path ?? '')) !== '')->count(),
        ];
    }

    public function approve(AutoBannedUnbanRequest $unbanRequest, User $reviewer, ?string $catatan = null): AutoBannedUnbanRequest
    {
        return $this->review($unbanRequest, $reviewer, AutoBannedUnbanStatus::Approved, $catatan);
    }

    public function reject(AutoBannedUnbanRequest $unbanRequest, User $reviewer, ?string $catatan = null): AutoBannedUnbanRequest
    {
        return $this->review($unbanRequest, $reviewer, AutoBannedUnbanStatus::Rejected, $catatan);
    }

    private function review(
        AutoBannedUnbanRequest $unbanRequest,
        User $reviewer,
        AutoBannedUnbanStatus $status,
        ?string $catatan,
    ): AutoBannedUnbanRequest {
        if ($unbanRequest->status !== AutoBannedUnbanStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Pengajuan ini sudah diproses sebelumnya.'],
            ]);
        }

        $unbanRequest->update([
            'status' => $status,
            'reviewed_by_id' => $reviewer->id,
            'reviewed_by_name' => trim((string) ($reviewer->name ?? 'SOD')),
            'reviewed_at' => now(),
            'catatan_review' => $catatan !== null && trim($catatan) !== '' ? trim($catatan) : null,
        ]);

        $unbanRequest = $unbanRequest->fresh();

        $this->syncSnapshotForRequest($unbanRequest);

        return $unbanRequest;
    }

    private function syncSnapshotForRequest(AutoBannedUnbanRequest $unbanRequest): void
    {
        if (! Schema::hasTable('auto_banned_status_snapshots')) {
            return;
        }

        $week = $this->normalizer->normalizeWeek((string) ($unbanRequest->week ?? ''));
        $year = trim((string) ($unbanRequest->iso_year ?? ''));

        $snapshot = AutoBannedStatusSnapshot::query()
            ->where('sid', $unbanRequest->sid)
            ->when($week !== '', fn (Builder $q) => $q->where('week', $week))
            ->when($year !== '', fn (Builder $q) => $q->where('iso_year', $year))
            ->first();

        if ($snapshot === null) {
            return;
        }

        $this->statusResolver->syncWorkflowFromUnbanRequests($snapshot);

        if ($snapshot->isDirty()) {
            $snapshot->save();
        }
    }

    /**
     * @param  Builder<AutoBannedUnbanRequest>  $query
     * @param  array{site?: string, week?: string, year?: string, perusahaan?: string, q?: string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $site = trim((string) ($filters['site'] ?? ''));
        if ($site !== '') {
            $query->where('site_dedicated', $site);
        }

        $perusahaan = trim((string) ($filters['perusahaan'] ?? ''));
        if ($perusahaan !== '') {
            $query->where('perusahaan', $perusahaan);
        }

        $week = $this->normalizer->normalizeWeek((string) ($filters['week'] ?? ''));
        if ($week !== '') {
            $query->where('week', $week);
        }

        $year = trim((string) ($filters['year'] ?? ''));
        if ($year !== '') {
            $query->where('iso_year', $year);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('sid', 'like', $like)
                    ->orWhere('karyawan', 'like', $like)
                    ->orWhere('perusahaan', 'like', $like)
                    ->orWhere('alasan_pengajuan', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like);
            });
        }
    }
}
