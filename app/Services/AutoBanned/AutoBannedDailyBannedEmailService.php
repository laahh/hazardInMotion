<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Mail\AutoBannedDailyBannedEmailMail;
use App\Models\AutoBannedDailyEmailLog;
use App\Models\ScrDailyBanned;
use App\Services\Mail\ResilientSmtpMailService;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AutoBannedDailyBannedEmailService
{
    public function __construct(
        private readonly AutoBannedDailyBannedExcelExporter $excelExporter,
        private readonly ResilientSmtpMailService $resilientMail,
    ) {}

    public function tablesAvailable(): bool
    {
        return Schema::hasTable(ScrDailyBannedColumns::TABLE)
            && Schema::hasTable('auto_banned_daily_email_logs');
    }

    public function scrTableAvailable(): bool
    {
        return Schema::hasTable(ScrDailyBannedColumns::TABLE);
    }

    /**
     * @return array{action: string, message: string, sent: bool, total: int}
     */
    public function runScheduled(bool $force = false): array
    {
        $batch = $this->resolveNextUnsentBatch();
        if ($batch === null) {
            return [
                'action' => 'skip',
                'message' => 'Tidak ada batch daily banned baru yang perlu dikirim.',
                'sent' => false,
                'total' => 0,
            ];
        }

        return $this->sendForBatch($batch, $force);
    }

    /**
     * @return array{action: string, message: string, sent: bool, total: int}
     */
    public function sendForDateAndShift(?string $filterDate = null, ?string $filterShift = null, bool $force = false): array
    {
        if (! $this->scrTableAvailable()) {
            return [
                'action' => 'error',
                'message' => 'Tabel scr_daily_banned belum tersedia.',
                'sent' => false,
                'total' => 0,
            ];
        }

        $batch = $this->resolveBatch($filterDate, $filterShift);
        if ($batch === null) {
            return [
                'action' => 'skip',
                'message' => 'Tidak ada data daily banned untuk periode yang diminta.',
                'sent' => false,
                'total' => 0,
            ];
        }

        return $this->sendForBatch($batch, $force);
    }

    /**
     * @return ?array{filter_date: string, filter_shift: string, scraped_at: string}
     */
    public function resolveNextUnsentBatch(): ?array
    {
        if (! $this->scrTableAvailable()) {
            return null;
        }

        $batches = ScrDailyBanned::query()
            ->selectRaw('filter_date, filter_shift, MAX(scraped_at) as scraped_at')
            ->groupBy('filter_date', 'filter_shift')
            ->orderByDesc('scraped_at')
            ->get();

        foreach ($batches as $batch) {
            $filterDate = $batch->filter_date instanceof Carbon
                ? $batch->filter_date->toDateString()
                : (string) $batch->filter_date;
            $filterShift = (string) $batch->filter_shift;

            if ($this->logTableAvailable() && $this->alreadySent($filterDate, $filterShift)) {
                continue;
            }

            return [
                'filter_date' => $filterDate,
                'filter_shift' => $filterShift,
                'scraped_at' => (string) $batch->scraped_at,
            ];
        }

        return null;
    }

    /**
     * @return ?array{filter_date: string, filter_shift: string, scraped_at: string}
     */
    public function resolveBatch(?string $filterDate = null, ?string $filterShift = null): ?array
    {
        $query = ScrDailyBanned::query()
            ->selectRaw('filter_date, filter_shift, MAX(scraped_at) as scraped_at')
            ->groupBy('filter_date', 'filter_shift');

        if ($filterDate !== null && $filterDate !== '') {
            $query->whereDate('filter_date', $filterDate);
        }

        if ($filterShift !== null && $filterShift !== '') {
            $query->where('filter_shift', $filterShift);
        }

        $batch = $query->orderByDesc('scraped_at')->first();
        if ($batch === null) {
            return null;
        }

        return [
            'filter_date' => $batch->filter_date instanceof Carbon
                ? $batch->filter_date->toDateString()
                : (string) $batch->filter_date,
            'filter_shift' => (string) $batch->filter_shift,
            'scraped_at' => (string) $batch->scraped_at,
        ];
    }

    /**
     * @param  array{filter_date: string, filter_shift: string, scraped_at: string}  $batch
     * @return array{action: string, message: string, sent: bool, total: int}
     */
    public function sendForBatch(array $batch, bool $force = false): array
    {
        if (! $this->scrTableAvailable()) {
            return [
                'action' => 'error',
                'message' => 'Tabel scr_daily_banned belum tersedia.',
                'sent' => false,
                'total' => 0,
            ];
        }

        $recipients = $this->recipients();
        if ($recipients === []) {
            return [
                'action' => 'error',
                'message' => 'AUTO_BANNED_DAILY_EMAILS belum dikonfigurasi.',
                'sent' => false,
                'total' => 0,
            ];
        }

        $filterDate = $batch['filter_date'];
        $filterShift = $batch['filter_shift'];
        $scrapedAt = $batch['scraped_at'];

        if (! $force && $this->logTableAvailable() && $this->alreadySent($filterDate, $filterShift)) {
            return [
                'action' => 'skip',
                'message' => "Email daily banned {$filterDate} ({$filterShift}) sudah pernah dikirim.",
                'sent' => false,
                'total' => 0,
            ];
        }

        $rows = $this->fetchBatchRows($filterDate, $filterShift, $scrapedAt);
        if ($rows->isEmpty()) {
            return [
                'action' => 'skip',
                'message' => 'Tidak ada data banned untuk dikirim.',
                'sent' => false,
                'total' => 0,
            ];
        }

        $lockSeconds = (int) config('auto_banned.daily_banned.send_lock_seconds', 600);
        $lock = Cache::lock('auto_banned:daily-banned-email-send', $lockSeconds);

        if (! $lock->get()) {
            return [
                'action' => 'skip',
                'message' => 'Proses kirim email daily banned sedang berjalan di proses lain.',
                'sent' => false,
                'total' => 0,
            ];
        }

        try {
            $payload = $this->excelExporter->formatRowsFromModels($rows);
            $summaries = $this->buildSummaries($payload);
            $sent = $this->dispatchMail(
                recipients: $recipients,
                filterDate: $filterDate,
                filterShift: $filterShift,
                scrapedAt: $scrapedAt,
                rows: $payload,
                summaries: $summaries,
            );

            if ($sent && $this->logTableAvailable()) {
                if ($force) {
                    AutoBannedDailyEmailLog::query()->updateOrCreate(
                        [
                            'filter_date' => $filterDate,
                            'filter_shift' => $filterShift,
                        ],
                        [
                            'scraped_at' => $scrapedAt,
                            'recipients' => implode(', ', $recipients),
                            'total_banned' => count($payload),
                            'perusahaan_count' => $summaries['perusahaanCount'],
                            'site_count' => $summaries['siteCount'],
                            'status' => 'sent',
                            'error_message' => null,
                            'sent_at' => now(),
                        ],
                    );
                } else {
                    AutoBannedDailyEmailLog::query()->create([
                        'filter_date' => $filterDate,
                        'filter_shift' => $filterShift,
                        'scraped_at' => $scrapedAt,
                        'recipients' => implode(', ', $recipients),
                        'total_banned' => count($payload),
                        'perusahaan_count' => $summaries['perusahaanCount'],
                        'site_count' => $summaries['siteCount'],
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                }
            }

            return [
                'action' => $sent ? 'sent' : 'error',
                'message' => $sent
                    ? "Email daily banned terkirim ({$filterDate}, {$filterShift}) — ".count($payload).' karyawan.'
                    : 'Gagal mengirim email daily banned.',
                'sent' => $sent,
                'total' => count($payload),
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $employees
     * @return array{rows: array<int, array{perusahaan: string, site: string, count: int}>, perusahaanCount: int, siteCount: int}
     */
    public function buildSummaries(array $employees): array
    {
        $collection = collect($employees);

        $rows = $collection
            ->groupBy(function (array $row): string {
                $perusahaan = trim((string) ($row['perusahaan'] ?? '')) !== '' ? trim((string) $row['perusahaan']) : '—';
                $site = trim((string) ($row['site'] ?? '')) !== '' ? trim((string) $row['site']) : '—';

                return $perusahaan.'|'.$site;
            })
            ->map(function ($group, string $key): array {
                [$perusahaan, $site] = array_pad(explode('|', $key, 2), 2, '—');

                return [
                    'perusahaan' => $perusahaan,
                    'site' => $site,
                    'count' => $group->count(),
                ];
            })
            ->sortBy([
                ['perusahaan', 'asc'],
                ['site', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'perusahaanCount' => $collection
                ->map(fn (array $row): string => trim((string) ($row['perusahaan'] ?? '')) !== '' ? trim((string) $row['perusahaan']) : '—')
                ->unique()
                ->count(),
            'siteCount' => $collection
                ->map(fn (array $row): string => trim((string) ($row['site'] ?? '')) !== '' ? trim((string) $row['site']) : '—')
                ->unique()
                ->count(),
        ];
    }

    /**
     * @return Collection<int, ScrDailyBanned>
     */
    private function fetchBatchRows(string $filterDate, string $filterShift, string $scrapedAt): Collection
    {
        return ScrDailyBanned::query()
            ->whereDate('filter_date', $filterDate)
            ->where('filter_shift', $filterShift)
            ->where('scraped_at', $scrapedAt)
            ->orderBy(ScrDailyBannedColumns::NAMA)
            ->get();
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array{rows: array<int, array{perusahaan: string, site: string, count: int}>, perusahaanCount: int, siteCount: int}  $summaries
     */
    private function dispatchMail(
        array $recipients,
        string $filterDate,
        string $filterShift,
        string $scrapedAt,
        array $rows,
        array $summaries,
    ): bool {
        $excelPath = '';
        $excelFilename = '';

        try {
            $excel = $this->excelExporter->createTempFile($rows, $filterDate, $filterShift);
            $excelPath = $excel['path'];
            $excelFilename = $excel['filename'];

            $scrapedAtFormatted = Carbon::parse($scrapedAt)
                ->timezone(config('auto_banned.daily_banned.timezone', 'Asia/Makassar'))
                ->format('d M Y H:i');

            $mailable = new AutoBannedDailyBannedEmailMail(
                filterDate: $filterDate,
                filterShift: $filterShift,
                scrapedAt: $scrapedAtFormatted,
                totalBanned: count($rows),
                summaryRows: $summaries['rows'],
                perusahaanCount: $summaries['perusahaanCount'],
                siteCount: $summaries['siteCount'],
                excelPath: $excelPath,
                excelFilename: $excelFilename,
                dashboardUrl: $this->dashboardUrl($filterDate),
            );

            foreach ($recipients as $email) {
                $this->resilientMail->send($mailable, $email);
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('AutoBanned daily banned email failed', [
                'filter_date' => $filterDate,
                'filter_shift' => $filterShift,
                'error' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            $this->excelExporter->deleteTempFile($excelPath);
        }
    }

    /**
     * @return array<int, string>
     */
    private function recipients(): array
    {
        $configured = config('auto_banned.daily_banned.recipients', []);
        if ($configured !== []) {
            return $configured;
        }

        return config('auto_banned.hsct.recipients', []);
    }

    private function dashboardUrl(string $filterDate): ?string
    {
        try {
            return route('auto-banned.banned-monitoring.index', ['filter_date' => $filterDate]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function logTableAvailable(): bool
    {
        return Schema::hasTable('auto_banned_daily_email_logs');
    }

    private function alreadySent(string $filterDate, string $filterShift): bool
    {
        return AutoBannedDailyEmailLog::query()
            ->whereDate('filter_date', $filterDate)
            ->where('filter_shift', $filterShift)
            ->exists();
    }
}
