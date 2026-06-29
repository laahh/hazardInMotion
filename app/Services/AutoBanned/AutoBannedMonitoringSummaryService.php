<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedSidAutomationStatus;
use App\Models\SidBannedLog;
use App\Models\SidUnbannedLog;
use App\Support\AutoBanned\AutoBannedSchema;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use Illuminate\Database\Eloquent\Builder;

class AutoBannedMonitoringSummaryService
{
    public function bannedLogTableAvailable(): bool
    {
        return AutoBannedSchema::hasSidBannedLogTable();
    }

    public function unbannedLogTableAvailable(): bool
    {
        return AutoBannedSchema::hasSidUnbannedLogTable();
    }

    /**
     * Ringkasan kumulatif dari awal (tanpa filter tanggal).
     *
     * @param  array{site?: string, perusahaan?: string, q?: string}  $filters
     * @return array{totalSudahDiBanned: int, totalMasihBanned: int, totalUnBanned: int}
     */
    public function buildSummaryStats(array $filters = []): array
    {
        return [
            'totalSudahDiBanned' => $this->countBannedLogTotal($filters),
            'totalMasihBanned' => $this->countStillBannedTotal($filters),
            'totalUnBanned' => $this->countUnbannedLogTotal($filters),
        ];
    }

    /**
     * @param  array{site?: string, perusahaan?: string, q?: string}  $filters
     */
    public function countBannedLogTotal(array $filters = []): int
    {
        if (! $this->bannedLogTableAvailable()) {
            return 0;
        }

        $query = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value);
        $this->applyBannedLogFilters($query, $filters);

        return (int) $query->count();
    }

    /**
     * @param  array{site?: string, perusahaan?: string, q?: string}  $filters
     */
    public function countUnbannedLogTotal(array $filters = []): int
    {
        if (! $this->unbannedLogTableAvailable()) {
            return 0;
        }

        $query = SidUnbannedLog::query();
        $this->applyUnbannedLogFilters($query, $filters);

        return (int) $query->count();
    }

    /**
     * Baris SUCCESS di sid_banned_log yang belum memiliki SID yang sama di sid_unbanned_log.
     *
     * @param  array{site?: string, perusahaan?: string, q?: string}  $filters
     */
    public function countStillBannedTotal(array $filters = []): int
    {
        if (! $this->bannedLogTableAvailable()) {
            return 0;
        }

        $query = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value);
        $this->applyBannedLogFilters($query, $filters);
        $this->excludeSidsPresentInUnbannedLog($query, $filters);

        return (int) $query->count();
    }

    /**
     * @param  array{site?: string, perusahaan?: string, q?: string}  $filters
     */
    private function excludeSidsPresentInUnbannedLog(Builder $query, array $filters): void
    {
        if (! $this->unbannedLogTableAvailable()) {
            return;
        }

        $unbannedSidQuery = SidUnbannedLog::query()
            ->select('sid')
            ->whereNotNull('sid')
            ->where('sid', '!=', '');

        $this->applyUnbannedLogFilters($unbannedSidQuery, $filters);

        $query->where(function (Builder $inner) use ($unbannedSidQuery): void {
            $inner->whereNull('sid')
                ->orWhere('sid', '=', '')
                ->orWhereNotIn('sid', $unbannedSidQuery);
        });
    }

    /**
     * @param  array{site?: string, perusahaan?: string, q?: string}  $filters
     */
    private function applyBannedLogFilters(Builder $query, array $filters): void
    {
        if (($filters['site'] ?? '') !== '') {
            $site = $filters['site'];
            $query->where(function (Builder $inner) use ($site): void {
                $inner->where('site_dedicated', $site);

                if (AutoBannedSchema::hasScrDailyBannedTable()) {
                    $inner->orWhereHas(
                        'scrDailyBanned',
                        fn (Builder $scr) => $scr->where(ScrDailyBannedColumns::SITE, $site),
                    );
                }
            });
        }

        if (($filters['perusahaan'] ?? '') !== '') {
            $query->where('perusahaan', $filters['perusahaan']);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('nik', 'like', $term)
                    ->orWhere('nama', 'like', $term)
                    ->orWhere('sid', 'like', $term)
                    ->orWhere('banned_reason', 'like', $term)
                    ->orWhere('error_message', 'like', $term);
            });
        }
    }

    /**
     * @param  array{site?: string, perusahaan?: string, q?: string}  $filters
     */
    private function applyUnbannedLogFilters(Builder $query, array $filters): void
    {
        if (($filters['site'] ?? '') !== '') {
            $site = $filters['site'];
            $query->where(function (Builder $inner) use ($site): void {
                $inner->where('site_dedicated', $site);

                if (AutoBannedSchema::hasScrDailyBannedTable()) {
                    $inner->orWhereHas(
                        'scrDailyBanned',
                        fn (Builder $scr) => $scr->where(ScrDailyBannedColumns::SITE, $site),
                    );
                }
            });
        }

        if (($filters['perusahaan'] ?? '') !== '') {
            $query->where('perusahaan', $filters['perusahaan']);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('nik', 'like', $term)
                    ->orWhere('nama', 'like', $term)
                    ->orWhere('sid', 'like', $term)
                    ->orWhere('banned_reason', 'like', $term)
                    ->orWhere('error_message', 'like', $term);
            });
        }
    }
}
