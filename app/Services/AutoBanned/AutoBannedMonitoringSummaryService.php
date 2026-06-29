<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedSidAutomationStatus;
use App\Models\SidBannedLog;
use App\Models\SidUnbanLog;
use App\Support\AutoBanned\AutoBannedSchema;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use Illuminate\Database\Eloquent\Builder;

class AutoBannedMonitoringSummaryService
{
    public function bannedLogTableAvailable(): bool
    {
        return AutoBannedSchema::hasSidBannedLogTable();
    }

    public function unbanLogTableAvailable(): bool
    {
        return AutoBannedSchema::hasSidUnbanLogTable();
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
            'totalUnBanned' => $this->countUnbanLogTotal($filters),
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
    public function countUnbanLogTotal(array $filters = []): int
    {
        if (! $this->unbanLogTableAvailable()) {
            return 0;
        }

        $query = SidUnbanLog::query();
        $this->applyUnbanLogFilters($query, $filters);

        return (int) $query->count();
    }

    /**
     * Baris SUCCESS di sid_banned_log yang belum memiliki SID yang sama di sid_unban_log.
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
        $this->excludeSidsPresentInUnbanLog($query, $filters);

        return (int) $query->count();
    }

    /**
     * @param  array{site?: string, perusahaan?: string, q?: string}  $filters
     */
    private function excludeSidsPresentInUnbanLog(Builder $query, array $filters): void
    {
        if (! $this->unbanLogTableAvailable()) {
            return;
        }

        $unbanSidQuery = SidUnbanLog::query()
            ->select('sid')
            ->whereNotNull('sid')
            ->where('sid', '!=', '');

        $this->applyUnbanLogFilters($unbanSidQuery, $filters);

        $query->where(function (Builder $inner) use ($unbanSidQuery): void {
            $inner->whereNull('sid')
                ->orWhere('sid', '=', '')
                ->orWhereNotIn('sid', $unbanSidQuery);
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
    private function applyUnbanLogFilters(Builder $query, array $filters): void
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
