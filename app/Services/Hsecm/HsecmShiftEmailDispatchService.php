<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use App\Mail\HsecmSummaryMail;
use App\Models\Hsecm\HsecmTasklist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class HsecmShiftEmailDispatchService
{
    public function __construct(
        private readonly HsecmDashboardService $dashboardService,
        private readonly HsecmDatabaseRepository $repository,
        private readonly HsecmWaRecipientRepository $recipientRepository,
        private readonly HsecmTasklistService $tasklistService,
    ) {}

    /**
     * Midshift: snapshot tengah shift.
     * - night → batch_slot 00:00
     * - day   → batch_slot 12:00
     * - auto  → pilih dari jam sekarang (WITA)
     *
     * @return array{sent: int, failed: int, skipped: int, message: string, details: list<array<string, mixed>>}
     */
    public function dispatchMidshift(
        bool $dryRun = false,
        ?Carbon $now = null,
        ?string $onlyEmail = null,
        ?string $overrideSite = null,
        ?string $overridePerusahaan = null,
        string $shift = 'auto',
    ): array {
        $now = ($now ?? now())->timezone('Asia/Makassar');
        $window = $this->resolveShiftWindow('midshift', $shift, $now);

        return $this->dispatchSnapshotMode(
            mode: 'midshift',
            slotHour: $window['slot_hour'],
            dataMode: 'snapshot',
            dryRun: $dryRun,
            now: $now,
            createTasklist: false,
            onlyEmail: $onlyEmail,
            overrideSite: $overrideSite,
            overridePerusahaan: $overridePerusahaan,
            shiftLabel: $window['label'],
        );
    }

    /**
     * Endshift: still-open vs midshift slot + buat tasklist.
     * - night → slot 06:00 vs 00:00
     * - day   → slot 18:00 vs 12:00
     * - auto  → pilih dari jam sekarang (WITA)
     *
     * @return array{sent: int, failed: int, skipped: int, message: string, details: list<array<string, mixed>>}
     */
    public function dispatchEndshift(
        bool $dryRun = false,
        ?Carbon $now = null,
        ?string $onlyEmail = null,
        ?string $overrideSite = null,
        ?string $overridePerusahaan = null,
        string $shift = 'auto',
    ): array {
        $now = ($now ?? now())->timezone('Asia/Makassar');
        $window = $this->resolveShiftWindow('endshift', $shift, $now);

        return $this->dispatchSnapshotMode(
            mode: 'endshift',
            slotHour: $window['slot_hour'],
            dataMode: 'still_open',
            dryRun: $dryRun,
            now: $now,
            createTasklist: true,
            previousSlotHour: $window['previous_slot_hour'],
            onlyEmail: $onlyEmail,
            overrideSite: $overrideSite,
            overridePerusahaan: $overridePerusahaan,
            shiftLabel: $window['label'],
        );
    }

    /**
     * Resolusi shift day/night untuk midshift & endshift.
     *
     * @return array{shift: string, slot_hour: int, previous_slot_hour: ?int, label: string}
     */
    public function resolveShiftWindow(string $kind, string $shift, Carbon $now): array
    {
        $shift = strtolower(trim($shift));
        if (! in_array($shift, ['auto', 'day', 'night'], true)) {
            throw new RuntimeException('Shift tidak valid. Gunakan auto|day|night.');
        }

        if ($shift === 'auto') {
            // 00–11 = malam, 12–23 = siang
            $shift = $now->hour < 12 ? 'night' : 'day';
        }

        if ($kind === 'midshift') {
            if ($shift === 'night') {
                return [
                    'shift' => 'night',
                    'slot_hour' => 0,
                    'previous_slot_hour' => null,
                    'label' => 'night/mid (slot 00:00)',
                ];
            }

            return [
                'shift' => 'day',
                'slot_hour' => 12,
                'previous_slot_hour' => null,
                'label' => 'day/mid (slot 12:00)',
            ];
        }

        // endshift
        if ($shift === 'night') {
            return [
                'shift' => 'night',
                'slot_hour' => 6,
                'previous_slot_hour' => 0,
                'label' => 'night/end (slot 06:00 vs 00:00)',
            ];
        }

        return [
            'shift' => 'day',
            'slot_hour' => 18,
            'previous_slot_hour' => 12,
            'label' => 'day/end (slot 18:00 vs 12:00)',
        ];
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, message: string, details: list<array<string, mixed>>}
     */
    public function dispatchEscalate(
        bool $dryRun = false,
        ?Carbon $now = null,
        ?string $onlyEmail = null,
    ): array {
        $now = ($now ?? now())->timezone('Asia/Makassar');

        if (! $this->tasklistService->tablesAvailable()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Tabel tasklist belum tersedia. Jalankan migration dulu.',
                'details' => [],
            ];
        }

        $due = $this->tasklistService->listDueForEscalate($now);
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $details = [];

        foreach ($due as $tasklist) {
            $summary = $this->tasklistService->escalateSummary($tasklist);
            if (($summary['open'] + $summary['submitted'] + $summary['rejected']) === 0) {
                $this->tasklistService->recomputeTasklistStatus($tasklist);
                $skipped++;
                $details[] = [
                    'scope' => $this->scopeLabel($tasklist->site, $tasklist->perusahaan),
                    'success' => true,
                    'message' => 'Skip: tidak ada item pending.',
                ];
                continue;
            }

            $recipients = $this->filterRecipientsByEmail(
                $this->matchRecipients($tasklist->site, $tasklist->perusahaan),
                $onlyEmail,
            );
            if ($recipients === []) {
                $skipped++;
                $details[] = [
                    'scope' => $this->scopeLabel($tasklist->site, $tasklist->perusahaan),
                    'success' => false,
                    'message' => $onlyEmail
                        ? 'Tidak ada penerima match --email untuk scope ini.'
                        : 'Tidak ada penerima untuk scope ini.',
                ];
                continue;
            }

            $filters = $this->baseFilters($tasklist->site, $tasklist->perusahaan);
            $narrative = [
                'exposure' => [],
                'gaps' => [[
                    'key' => 'escalate-pending',
                    'title' => 'Item tasklist belum selesai ACC',
                    'value' => (string) count($summary['pending_items']),
                    'available' => true,
                    'total' => count($summary['pending_items']),
                    'truncated' => false,
                    'columns' => [
                        ['key' => 'title', 'label' => 'Program'],
                        ['key' => 'value_label', 'label' => 'Item'],
                        ['key' => 'status', 'label' => 'Status'],
                        ['key' => 'rejection_reason', 'label' => 'Alasan Tolak'],
                    ],
                    'rows' => array_map(static fn (array $r): array => [
                        'title' => (string) ($r['title'] ?? ''),
                        'value_label' => (string) ($r['value_label'] ?? ''),
                        'status' => (string) ($r['status'] ?? ''),
                        'rejection_reason' => (string) ($r['rejection_reason'] ?? '—'),
                    ], $summary['pending_items']),
                    'action' => 'Lengkapi evidence / resubmit item yang ditolak, lalu tunggu ACC HSE.',
                    'tone' => 'danger',
                    'needs_action' => true,
                    'detail_url' => null,
                ]],
                'escalate_counts' => $summary,
            ];

            $tasklistUrl = $this->tasklistService->publicUrl($tasklist);
            $scopeSent = 0;
            $scopeFailed = 0;

            foreach ($recipients as $recipient) {
                $result = $this->sendOne(
                    mode: 'escalate',
                    recipient: $recipient,
                    filters: $filters,
                    narrative: $narrative,
                    ctaUrl: $tasklistUrl,
                    dryRun: $dryRun,
                    batchSlotLabel: optional($tasklist->batch_slot)?->format('d/m/Y H:i') ?? '',
                    escalateCount: (int) $tasklist->escalate_count + 1,
                );

                if ($result['success']) {
                    $scopeSent++;
                    $sent++;
                } else {
                    $scopeFailed++;
                    $failed++;
                }
                $details[] = $result;
                usleep(200000);
            }

            if (! $dryRun && $scopeSent > 0) {
                $this->tasklistService->markEscalated($tasklist, $now);
            } elseif ($scopeSent === 0 && $scopeFailed === 0) {
                $skipped++;
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => "Escalate: {$sent} terkirim, {$failed} gagal, {$skipped} skip.",
            'details' => $details,
        ];
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, message: string, details: list<array<string, mixed>>}
     */
    private function dispatchSnapshotMode(
        string $mode,
        int $slotHour,
        string $dataMode,
        bool $dryRun,
        ?Carbon $now,
        bool $createTasklist,
        ?int $previousSlotHour = null,
        ?string $onlyEmail = null,
        ?string $overrideSite = null,
        ?string $overridePerusahaan = null,
        string $shiftLabel = '',
    ): array {
        $now = ($now ?? now())->timezone('Asia/Makassar');
        $probeTable = $this->probeTable();

        if (! $this->repository->hasBatchSlotSupport($probeTable)) {
            throw new RuntimeException(
                "Kolom batch_slot belum ada di {$probeTable}. Jalankan alter SQL scrap HSECM sebelum mengirim email terjadwal."
            );
        }

        $target = $now->copy()->startOfDay()->setTime($slotHour, 0, 0);
        // Endshift malam jam 06: target hari ini 06:00. Midshift malam jam 00: target hari ini 00:00.
        $batchSlot = $this->repository->resolveBatchSlotAtOrBefore($probeTable, $target);
        if ($batchSlot === null) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => "Tidak ada data batch_slot <= {$target->format('Y-m-d H:i')}".($shiftLabel !== '' ? " [{$shiftLabel}]" : '').'.',
                'details' => [],
            ];
        }

        $previousSlot = null;
        if ($previousSlotHour !== null) {
            $prevTarget = $now->copy()->startOfDay()->setTime($previousSlotHour, 0, 0);
            $previousSlot = $this->repository->resolveBatchSlotAtOrBefore($probeTable, $prevTarget);
            if ($previousSlot !== null && $previousSlot >= $batchSlot) {
                $previousSlot = $this->repository->previousBatchSlot($probeTable, $batchSlot);
            }
        }

        $recipients = $this->resolveRecipientsForDispatch($onlyEmail, $overrideSite, $overridePerusahaan);
        if ($recipients === []) {
            return [
                'sent' => 0,
                'failed' => 1,
                'skipped' => 0,
                'message' => 'Tidak ada penerima. Pastikan --email valid, atau daftarkan di config/UI.',
                'details' => [[
                    'nama' => '-',
                    'email' => (string) ($onlyEmail ?? ''),
                    'success' => false,
                    'message' => 'Penerima tidak ditemukan.',
                ]],
            ];
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $details = [];
        /** @var array<string, HsecmTasklist|null> $tasklistCache */
        $tasklistCache = [];

        foreach ($recipients as $recipient) {
            $site = $this->normalizeNullable($recipient['site'] ?? null);
            $perusahaan = trim((string) ($recipient['perusahaan'] ?? ''));
            if ($perusahaan === '') {
                $skipped++;
                continue;
            }

            $filters = $this->dashboardService->withBatchContext(
                $this->baseFilters($site, $perusahaan),
                $batchSlot,
                $dataMode,
                $previousSlot,
            );

            $summary = $this->dashboardService->buildScopeSummary($filters);
            $narrative = $summary['email_narrative'] ?? ['exposure' => [], 'gaps' => []];

            $ctaUrl = $this->buildPublicUrl('/hsecm/pjo-action', [
                'site' => $site,
                'perusahaan' => $perusahaan,
            ]);

            if ($createTasklist) {
                $cacheKey = ($site ?? '').'|'.$perusahaan;
                if (! array_key_exists($cacheKey, $tasklistCache)) {
                    $gapItems = $this->dashboardService->extractTasklistItemsFromGaps($filters);
                    if ($gapItems === []) {
                        $tasklistCache[$cacheKey] = null;
                    } elseif ($dryRun) {
                        $tasklistCache[$cacheKey] = null;
                        $details[] = [
                            'nama' => '(dry-run tasklist)',
                            'email' => '-',
                            'success' => true,
                            'message' => 'Dry-run: akan buat tasklist '.$cacheKey.' ('.count($gapItems).' items)',
                        ];
                    } else {
                        $tasklistCache[$cacheKey] = $this->tasklistService->createFromEndshift(
                            $batchSlot,
                            ['site' => $site ?? '', 'perusahaan' => $perusahaan],
                            $gapItems,
                        );
                    }
                }

                // CTA email pasca-shift = Aksi PJO (daily monitoring), bukan link tasklist.
                $hasGapAction = collect($narrative['gaps'] ?? [])->contains(
                    fn (array $g): bool => ($g['needs_action'] ?? false) && ((int) ($g['total'] ?? 0)) > 0
                );
                if (! $hasGapAction && ($tasklistCache[$cacheKey] ?? null) === null) {
                    $skipped++;
                    $details[] = [
                        'nama' => (string) ($recipient['nama'] ?? ''),
                        'email' => (string) ($recipient['email'] ?? ''),
                        'success' => true,
                        'message' => 'Skip: tidak ada gap still-open.',
                    ];
                    continue;
                }
            }

            $result = $this->sendOne(
                mode: $mode,
                recipient: $recipient,
                filters: $filters,
                narrative: $this->rewriteNarrativePublicUrls($narrative),
                ctaUrl: $ctaUrl,
                dryRun: $dryRun,
                batchSlotLabel: Carbon::parse($batchSlot)->format('d/m/Y H:i'),
            );

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }
            $details[] = $result;
            usleep(200000);
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => strtoupper($mode).': '.$sent.' terkirim, '.$failed.' gagal, '.$skipped.' skip. Slot='.$batchSlot.
                ($previousSlot ? " prev={$previousSlot}" : '').
                ($shiftLabel !== '' ? " [{$shiftLabel}]" : ''),
            'details' => $details,
        ];
    }

    /**
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>  $filters
     * @param  array{exposure?: list<array<string, mixed>>, gaps?: list<array<string, mixed>>}  $narrative
     * @return array{nama: string, email: string, success: bool, message: string}
     */
    private function sendOne(
        string $mode,
        array $recipient,
        array $filters,
        array $narrative,
        string $ctaUrl,
        bool $dryRun,
        string $batchSlotLabel = '',
        int $escalateCount = 0,
    ): array {
        $nama = (string) ($recipient['nama'] ?? '-');
        $email = trim((string) ($recipient['email'] ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'nama' => $nama,
                'email' => $email,
                'success' => false,
                'message' => 'Email tidak valid.',
            ];
        }

        if ($dryRun) {
            return [
                'nama' => $nama,
                'email' => $email,
                'success' => true,
                'message' => "Dry-run {$mode} → {$email} (cta={$ctaUrl})",
            ];
        }

        try {
            Mail::to($email)->send(new HsecmSummaryMail(
                recipient: [
                    'nama' => $nama,
                    'role' => (string) ($recipient['role'] ?? ''),
                    'site' => $recipient['site'] ?? null,
                    'perusahaan' => (string) ($recipient['perusahaan'] ?? ''),
                    'no' => (string) ($recipient['no'] ?? ''),
                    'email' => $email,
                ],
                scope: [
                    'site' => (string) ($filters['site'] ?? ''),
                    'perusahaan' => (string) ($filters['perusahaan'] ?? ''),
                    'week' => (string) ($filters['week'] ?? ''),
                    'year' => (string) ($filters['year'] ?? ''),
                    'batch_slot' => (string) ($filters['batch_slot'] ?? ''),
                ],
                emailNarrative: $narrative,
                dashboardUrl: $ctaUrl,
                generatedAt: now()->timezone('Asia/Makassar')->format('d/m/Y H:i').' WITA',
                mode: $mode,
                batchSlotLabel: $batchSlotLabel,
                escalateCount: $escalateCount,
            ));

            return [
                'nama' => $nama,
                'email' => $email,
                'success' => true,
                'message' => "Terkirim ({$mode}).",
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'nama' => $nama,
                'email' => $email,
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveRecipientsForDispatch(
        ?string $onlyEmail,
        ?string $overrideSite = null,
        ?string $overridePerusahaan = null,
    ): array {
        $all = $this->recipientRepository->all();
        $onlyEmail = $this->normalizeEmail($onlyEmail);
        if ($onlyEmail === null) {
            return $all;
        }

        $matched = $this->filterRecipientsByEmail($all, $onlyEmail);
        if ($matched !== []) {
            return $matched;
        }

        // Email belum terdaftar: kirim 1x sebagai penerima uji (override).
        $site = $this->normalizeNullable($overrideSite);
        $perusahaan = trim((string) ($overridePerusahaan ?? ''));
        if ($perusahaan === '') {
            $first = $all[0] ?? null;
            if (is_array($first)) {
                $site = $this->normalizeNullable($first['site'] ?? null);
                $perusahaan = trim((string) ($first['perusahaan'] ?? ''));
            }
        }

        return [[
            'nama' => 'Test Recipient',
            'role' => 'TEST',
            'site' => $site,
            'perusahaan' => $perusahaan !== '' ? $perusahaan : 'TEST Scope',
            'no' => '',
            'email' => $onlyEmail,
            'source' => 'cli-override',
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $recipients
     * @return list<array<string, mixed>>
     */
    private function filterRecipientsByEmail(array $recipients, ?string $onlyEmail): array
    {
        $onlyEmail = $this->normalizeEmail($onlyEmail);
        if ($onlyEmail === null) {
            return $recipients;
        }

        return collect($recipients)
            ->filter(fn (array $r): bool => $this->normalizeEmail($r['email'] ?? null) === $onlyEmail)
            ->values()
            ->all();
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $email = strtolower(trim((string) $value));

        return $email !== '' ? $email : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function matchRecipients(?string $site, string $perusahaan): array
    {
        return collect($this->recipientRepository->all())
            ->filter(function (array $r) use ($site, $perusahaan): bool {
                if (! $this->softEqual((string) ($r['perusahaan'] ?? ''), $perusahaan)) {
                    return false;
                }
                $rSite = $this->normalizeNullable($r['site'] ?? null);
                if ($site === null || $site === '') {
                    return $rSite === null;
                }
                if ($rSite === null) {
                    return true;
                }

                return $this->softEqual($rSite, $site);
            })
            ->values()
            ->all();
    }

    /**
     * @return array{site: string, perusahaan: string, week: string, year: string, date_from: string, date_to: string, q: string}
     */
    private function baseFilters(?string $site, string $perusahaan): array
    {
        return [
            'site' => $site ?? '',
            'perusahaan' => $perusahaan,
            'week' => '',
            'year' => '',
            'date_from' => '',
            'date_to' => '',
            'q' => '',
        ];
    }

    private function probeTable(): string
    {
        return HsecmDashboardService::DATASETS['sap-rfid']['table'];
    }

    /**
     * @param  array<string, string|null>  $query
     */
    private function buildPublicUrl(string $path, array $query = []): string
    {
        $base = rtrim((string) config('hsecm.public_url', 'https://besentry-dev.beraucoal.co.id'), '/');
        $path = '/'.ltrim($path, '/');
        $filtered = array_filter(
            $query,
            static fn ($v) => $v !== null && trim((string) $v) !== ''
        );

        if ($filtered === []) {
            return $base.$path;
        }

        return $base.$path.'?'.http_build_query($filtered);
    }

    /**
     * @param  array{exposure?: list<array<string, mixed>>, gaps?: list<array<string, mixed>>}  $narrative
     * @return array{exposure: list<array<string, mixed>>, gaps: list<array<string, mixed>>}
     */
    private function rewriteNarrativePublicUrls(array $narrative): array
    {
        foreach (['exposure', 'gaps'] as $group) {
            foreach ($narrative[$group] ?? [] as $i => $section) {
                $detailUrl = trim((string) ($section['detail_url'] ?? ''));
                if ($detailUrl === '') {
                    continue;
                }
                $parts = parse_url($detailUrl);
                $path = (string) ($parts['path'] ?? '');
                $query = [];
                if (! empty($parts['query'])) {
                    parse_str($parts['query'], $query);
                }
                $narrative[$group][$i]['detail_url'] = $this->buildPublicUrl($path, $query);
            }
        }

        $narrative['exposure'] = $narrative['exposure'] ?? [];
        $narrative['gaps'] = $narrative['gaps'] ?? [];

        return $narrative;
    }

    private function normalizeNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim((string) $value);

        return $t === '' ? null : $t;
    }

    private function softEqual(string $a, string $b): bool
    {
        if (strcasecmp($a, $b) === 0) {
            return true;
        }
        $na = Str::lower(preg_replace('/[^a-z0-9]/i', '', $a) ?? '');
        $nb = Str::lower(preg_replace('/[^a-z0-9]/i', '', $b) ?? '');

        return $na !== '' && $na === $nb;
    }

    private function scopeLabel(?string $site, string $perusahaan): string
    {
        return ($site ?: 'Semua Site').' · '.$perusahaan;
    }
}
