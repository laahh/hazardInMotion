<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use App\Mail\HsecmSummaryMail;
use App\Services\FonnteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class HsecmWaNotifyService
{
    public function __construct(
        private readonly HsecmDashboardService $dashboardService,
        private readonly FonnteService $fonnteService,
        private readonly HsecmWaRecipientRepository $recipientRepository,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function recipients(): array
    {
        return $this->recipientRepository->all();
    }

    /**
     * @param  array{nama: string, email: string, site?: ?string, perusahaan?: string, role?: string, no?: string}  $payload
     * @return array<string, mixed>
     */
    public function addRecipient(array $payload): array
    {
        return $this->recipientRepository->add($payload);
    }

    /**
     * @param  array{nama: string, email: string, site?: ?string, perusahaan?: string, role?: string, no?: string}  $payload
     * @return array<string, mixed>|null
     */
    public function updateRecipient(string $id, array $payload): ?array
    {
        return $this->recipientRepository->update($id, $payload);
    }

    public function deleteRecipient(string $id): bool
    {
        return $this->recipientRepository->delete($id);
    }

    /**
     * @return list<array{
     *   index: int,
     *   site: ?string,
     *   perusahaan: string,
     *   role: string,
     *   nama: string,
     *   no: string,
     *   email: string,
     *   phone_normalized: string,
     *   wa_url: string,
     *   message: string,
     *   kpis: list<array<string, mixed>>,
     *   total_records: int
     * }>
     */
    public function buildRecipientRows(Request $request): array
    {
        $periodFilters = [
            'week' => trim((string) $request->input('week', $request->query('week', ''))),
            'year' => trim((string) $request->input('year', $request->query('year', ''))),
            'date_from' => trim((string) $request->input('date_from', $request->query('date_from', ''))),
            'date_to' => trim((string) $request->input('date_to', $request->query('date_to', ''))),
            'q' => '',
        ];

        $filterOptions = $this->dashboardService->buildScopeSummary([
            'site' => '',
            'perusahaan' => '',
            'week' => $periodFilters['week'],
            'year' => $periodFilters['year'],
            'date_from' => $periodFilters['date_from'],
            'date_to' => $periodFilters['date_to'],
            'q' => '',
        ])['filter_options'];

        /** @var array<string, array{kpis: list<array<string, mixed>>, datasets: list<array<string, mixed>>, by_company: list<array<string, mixed>>}> $summaryCache */
        $summaryCache = [];

        return collect($this->recipientRepository->all())
            ->values()
            ->map(function (array $recipient, int $index) use ($periodFilters, $filterOptions, &$summaryCache): array {
                $site = $this->normalizeNullableString($recipient['site'] ?? null);
                $perusahaan = trim((string) ($recipient['perusahaan'] ?? ''));
                $resolvedSite = $this->resolveAgainstOptions($site, $filterOptions['sites'] ?? []);
                $resolvedCompany = $this->resolveAgainstOptions($perusahaan, $filterOptions['companies'] ?? []);

                $filters = [
                    'site' => $site === null ? '' : ($resolvedSite ?? ''),
                    'perusahaan' => $resolvedCompany ?? $perusahaan,
                    'week' => $periodFilters['week'],
                    'year' => $periodFilters['year'],
                    'date_from' => $periodFilters['date_from'],
                    'date_to' => $periodFilters['date_to'],
                    'q' => '',
                ];

                $cacheKey = implode('|', [
                    $filters['site'],
                    $filters['perusahaan'],
                    $filters['week'],
                    $filters['year'],
                    $filters['date_from'],
                    $filters['date_to'],
                ]);
                if (! isset($summaryCache[$cacheKey])) {
                    $summaryCache[$cacheKey] = $this->dashboardService->buildScopeSummary($filters);
                }
                $summary = $summaryCache[$cacheKey];

                $message = $this->composeMessage($recipient, $filters, $summary);
                $phone = $this->fonnteService->normalizePhoneNumber((string) ($recipient['no'] ?? ''));

                return [
                    'index' => $index,
                    'id' => (string) ($recipient['id'] ?? ''),
                    'source' => (string) ($recipient['source'] ?? 'config'),
                    'editable' => (bool) ($recipient['editable'] ?? false),
                    'deletable' => (bool) ($recipient['deletable'] ?? (($recipient['source'] ?? '') === 'custom')),
                    'site' => $site,
                    'perusahaan' => $perusahaan,
                    'role' => (string) ($recipient['role'] ?? ''),
                    'nama' => (string) ($recipient['nama'] ?? ''),
                    'no' => (string) ($recipient['no'] ?? ''),
                    'email' => (string) ($recipient['email'] ?? ''),
                    'phone_normalized' => $phone,
                    'resolved_site' => $filters['site'] !== '' ? $filters['site'] : null,
                    'resolved_perusahaan' => $filters['perusahaan'],
                    'message' => $message,
                    'wa_url' => $phone !== ''
                        ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message)
                        : '',
                    'kpis' => $summary['kpis'],
                    'datasets' => $summary['datasets'],
                    'by_company' => $summary['by_company'] ?? [],
                    'email_narrative' => $summary['email_narrative'] ?? ['exposure' => [], 'gaps' => []],
                    'scope' => [
                        'site' => $filters['site'],
                        'perusahaan' => $filters['perusahaan'],
                        'week' => $filters['week'],
                        'year' => $filters['year'],
                        'date_from' => $filters['date_from'],
                        'date_to' => $filters['date_to'],
                    ],
                    'total_records' => collect($summary['datasets'])->sum('count'),
                ];
            })
            ->all();
    }

    /**
     * @return array{success: bool, message: string, channel: string}
     */
    public function sendEmail(int $index, Request $request): array
    {
        $rows = $this->buildRecipientRows($request);
        $row = collect($rows)->firstWhere('index', $index);

        if ($row === null) {
            return [
                'success' => false,
                'message' => 'Kontak tidak ditemukan.',
                'channel' => 'email',
            ];
        }

        return $this->dispatchEmailRow($row);
    }

    /**
     * Kirim email satu per satu ke indeks terpilih.
     *
     * @param  list<int>  $indexes
     * @return array{success: bool, message: string, channel: string, sent: int, failed: int, details: list<array{nama: string, email: string, success: bool, message: string}>}
     */
    public function sendEmails(array $indexes, Request $request): array
    {
        $rows = collect($this->buildRecipientRows($request))->keyBy('index');
        $details = [];
        $sent = 0;
        $failed = 0;

        $uniqueIndexes = collect($indexes)
            ->map(static fn ($v): int => (int) $v)
            ->unique()
            ->sort()
            ->values();

        foreach ($uniqueIndexes as $index) {
            $row = $rows->get($index);
            if ($row === null) {
                $failed++;
                $details[] = [
                    'nama' => '#'.$index,
                    'email' => '-',
                    'success' => false,
                    'message' => 'Kontak tidak ditemukan',
                ];
                continue;
            }

            $result = $this->dispatchEmailRow($row);
            $details[] = [
                'nama' => $row['nama'],
                'email' => $row['email'],
                'success' => $result['success'],
                'message' => $result['message'],
            ];

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }

            // jeda singkat antar kirim agar tidak membanjiri SMTP
            usleep(250000);
        }

        $message = $sent > 0
            ? "Email terkirim: {$sent} berhasil".($failed > 0 ? ", {$failed} gagal." : '.')
            : 'Tidak ada email yang berhasil dikirim.'.($failed > 0 ? " {$failed} gagal." : '');

        return [
            'success' => $sent > 0 && $failed === 0,
            'message' => $message,
            'channel' => 'email',
            'sent' => $sent,
            'failed' => $failed,
            'details' => $details,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{success: bool, message: string, channel: string}
     */
    private function dispatchEmailRow(array $row): array
    {
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Email tidak valid untuk '.$row['nama'].'.',
                'channel' => 'email',
            ];
        }

        try {
            Mail::to($email)->send(new HsecmSummaryMail(
                recipient: [
                    'nama' => $row['nama'],
                    'role' => $row['role'],
                    'site' => $row['site'],
                    'perusahaan' => $row['perusahaan'],
                    'no' => $row['no'],
                    'email' => $row['email'],
                ],
                scope: $row['scope'],
                emailNarrative: $this->rewriteNarrativePublicUrls(
                    $row['email_narrative'] ?? ['exposure' => [], 'gaps' => []]
                ),
                dashboardUrl: $this->buildHsecmPublicUrl('/hsecm/pjo-action', [
                    'site' => trim((string) ($row['scope']['site'] ?? '')) !== ''
                        ? (string) $row['scope']['site']
                        : null,
                    'perusahaan' => trim((string) ($row['scope']['perusahaan'] ?? '')) !== ''
                        ? (string) $row['scope']['perusahaan']
                        : null,
                ]),
                generatedAt: now()->timezone(config('app.timezone', 'Asia/Makassar'))->format('d/m/Y H:i').' WITA',
                mode: 'midshift',
            ));

            return [
                'success' => true,
                'message' => 'Email berhasil dikirim ke '.$row['nama'].' ('.$email.').',
                'channel' => 'email',
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Gagal kirim email ke '.$row['nama'].': '.$e->getMessage(),
                'channel' => 'email',
            ];
        }
    }

    /**
     * @return array{success: bool, message: string, wa_url?: string, channel: string}
     */
    public function send(int $index, Request $request, string $channel = 'wa_me'): array
    {
        $rows = $this->buildRecipientRows($request);
        $row = collect($rows)->firstWhere('index', $index);

        if ($row === null) {
            return [
                'success' => false,
                'message' => 'Kontak tidak ditemukan.',
                'channel' => $channel,
            ];
        }

        if ($row['phone_normalized'] === '') {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp tidak valid untuk '.$row['nama'].'.',
                'channel' => $channel,
            ];
        }

        if ($channel === 'fonnte') {
            $result = $this->fonnteService->sendMessage($row['phone_normalized'], $row['message']);

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => ($result['success'] ?? false)
                    ? 'Pesan berhasil dikirim via Fonnte ke '.$row['nama'].'.'
                    : 'Gagal kirim Fonnte: '.($result['response']['error'] ?? $result['status'] ?? 'unknown'),
                'channel' => 'fonnte',
                'wa_url' => $row['wa_url'],
            ];
        }

        return [
            'success' => true,
            'message' => 'Membuka WhatsApp untuk '.$row['nama'].'.',
            'channel' => 'wa_me',
            'wa_url' => $row['wa_url'],
        ];
    }

    public function fonnteConfigured(): bool
    {
        return trim((string) config('services.fonnte.token', '')) !== '';
    }

    /**
     * Format WA selaras narasi email Daily Monitoring & Intervensi.
     *
     * @param  array<string, mixed>  $recipient
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @param  array{
     *     kpis?: list<array<string, mixed>>,
     *     datasets?: list<array<string, mixed>>,
     *     email_narrative?: array{exposure?: list<array<string, mixed>>, gaps?: list<array<string, mixed>>}
     * }  $summary
     */
    private function composeMessage(array $recipient, array $filters, array $summary): string
    {
        $nama = (string) ($recipient['nama'] ?? '-');
        $role = trim((string) ($recipient['role'] ?? ''));
        if ($role === '') {
            $role = 'PENANGGUNG JAWAB OPERASIONAL';
        }
        $siteLabel = $filters['site'] !== '' ? $filters['site'] : (($recipient['site'] ?? null) ?: 'Semua Site');
        $companyLabel = $filters['perusahaan'] !== '' ? $filters['perusahaan'] : ((string) ($recipient['perusahaan'] ?? '-'));

        $narrative = $summary['email_narrative'] ?? ['exposure' => [], 'gaps' => []];
        $exposure = collect($narrative['exposure'] ?? [])
            ->filter(static fn (array $s): bool => (bool) ($s['available'] ?? true))
            ->values()
            ->all();
        $gaps = collect($narrative['gaps'] ?? [])
            ->filter(static fn (array $s): bool => (bool) ($s['available'] ?? true))
            ->values()
            ->all();

        $pjoUrl = $this->buildHsecmPublicUrl('/hsecm/pjo-action', [
            'site' => $filters['site'] !== '' ? $filters['site'] : null,
            'perusahaan' => $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
        ]);

        $lines = [
            '*Daily Monitoring & Intervensi*',
            $siteLabel.' · '.$companyLabel,
            '',
            'Yth. *'.$nama.'*',
            '',
            $role.' · Berikut ringkasan highlight gap untuk scope site & perusahaan Anda sebagai *Monitoring & Intervensi* berdasarkan shift yang sudah berjalan sebelumnya.',
            '',
            'Berikut kami sampaikan exposure dari shift yang sudah berjalan sebelumnya:',
        ];

        $expNo = 1;
        foreach ($exposure as $section) {
            $lines[] = $this->formatNarrativeWaLine($expNo, $section);
            $expNo++;
        }
        if ($exposure === []) {
            $lines[] = '_Tidak ada item exposure pada scope ini._';
        }

        $lines[] = '';
        $lines[] = 'Berikut kami sampaikan gap yang menjadi concern agar segera ditindaklanjuti pasca shift berakhir:';

        $gapNo = 1;
        foreach ($gaps as $section) {
            $lines[] = $this->formatNarrativeWaLine($gapNo, $section);
            $gapNo++;
        }
        if ($gaps === []) {
            $lines[] = '_Tidak ada gap concern pada scope ini._';
        }

        $lines[] = '';
        $lines[] = 'Detail data secara overall dapat diakses pada Website berikut:';
        $lines[] = $pjoUrl;
        $lines[] = '';
        $lines[] = 'Mohon setiap point dari gap yang muncul di atas dapat dikontrol dan ditindaklanjuti untuk diperbaiki agar tidak terjadi perulangan terhadap gap yang sama pada shift berikutnya.';
        $lines[] = '';
        $lines[] = '_'.now()->timezone(config('app.timezone', 'Asia/Makassar'))->format('d/m/Y H:i').' WITA_';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function formatNarrativeWaLine(int $number, array $section): string
    {
        $title = (string) ($section['title'] ?? '-');
        $value = (string) ($section['value'] ?? '—');
        $action = trim((string) ($section['action'] ?? ''));

        $line = $number.'. '.$title.': *'.$value.'*';
        if ($action !== '') {
            $line .= ' — '.$action;
        }

        return $line;
    }

    /**
     * Absolute URL publik untuk link di email (bukan APP_URL lokal).
     *
     * @param  array<string, string|null>  $query
     */
    private function buildHsecmPublicUrl(string $path, array $query = []): string
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
            $sections = $narrative[$group] ?? [];
            foreach ($sections as $i => $section) {
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

                $narrative[$group][$i]['detail_url'] = $this->buildHsecmPublicUrl($path, $query);
            }
        }

        $narrative['exposure'] = $narrative['exposure'] ?? [];
        $narrative['gaps'] = $narrative['gaps'] ?? [];

        return $narrative;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  list<string>  $options
     */
    private function resolveAgainstOptions(?string $value, array $options): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach ($options as $option) {
            if (strcasecmp((string) $option, $value) === 0) {
                return (string) $option;
            }
        }

        // Soft match: hilangkan spasi & karakter non-alfanumerik
        $needle = Str::lower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');
        foreach ($options as $option) {
            $hay = Str::lower(preg_replace('/[^a-z0-9]/i', '', (string) $option) ?? '');
            if ($needle !== '' && $hay === $needle) {
                return (string) $option;
            }
        }

        return $value;
    }
}
