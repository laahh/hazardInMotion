<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Actions\Isc\IscPobClassifyAction;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan Excel daftar personel / RFID dari snapshot POB.
 */
final class IscPobRosterExcelService
{
    /**
     * @var list<string>
     */
    public const TYPES = [
        'in', 'out', 'unknown', 'safe', 'unsafe', 'checkin',
        'both', 'gap_br', 'gap_rb', 'current', 'kind',
    ];

    public function __construct(
        private readonly IscPobSnapshotService $snapshot,
    ) {}

    public function download(
        bool $demo,
        string $type,
        ?string $safety = null,
        ?string $kind = null,
        ?string $site = null,
    ): StreamedResponse {
        $type = $this->normalizeType($type, $safety, $kind);
        $pack = $this->snapshot->snapshot(true, $demo);
        $rows = $this->rows($pack, $type, $kind, $site);
        $title = $this->sheetTitle($type);
        $filename = 'isc-roster-'.$type.'-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $headers = ['No', 'Nama', 'SID', 'Perusahaan', 'Site', 'Status', 'Keselamatan', 'GPS', 'Check-in'];
        foreach ($headers as $index => $header) {
            $cell = $sheet->getCell([$index + 1, 1]);
            $cell->setValue($header);
            $sheet->getStyle($cell->getCoordinate())->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F0FE'],
                ],
            ]);
        }
        foreach ($rows as $i => $row) {
            $sheet->fromArray([
                $i + 1,
                $row['name'],
                $row['sid'],
                $row['company'] ?? '—',
                $row['site'] ?? '—',
                $row['status'] ?? '—',
                $row['safety'] ?? '—',
                $row['gps'] ?? '—',
                $row['checked_in_at'] ?? '—',
            ], null, 'A'.($i + 2));
        }
        foreach (range(1, 9) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return list<array<string, mixed>>
     */
    public function rows(array $pack, string $type, ?string $kind = null, ?string $site = null): array
    {
        $site = $site !== null && $site !== '' ? strtoupper($site) : null;
        $people = is_array($pack['people'] ?? null) ? $pack['people'] : [];
        $checkins = is_array($pack['checkins'] ?? null) ? $pack['checkins'] : [];
        $recon = is_array($pack['reconcile'] ?? null) ? $pack['reconcile'] : [];

        return match ($type) {
            'checkin' => $this->fromCheckins($checkins, $site),
            'both' => $this->fromReconcile($recon['both'] ?? [], 'RFID + Besigma', $site),
            'gap_br' => $this->fromReconcile($recon['gap_besigma_minus_rfid'] ?? [], 'Besigma tanpa RFID', $site),
            'gap_rb' => $this->fromReconcile($recon['gap_rfid_minus_besigma'] ?? [], 'RFID tanpa Besigma', $site),
            'current' => $this->fromReconcile($recon['current_list'] ?? [], 'GPS aktif', $site),
            default => $this->fromPeople($people, $type, $kind, $site),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return list<array<string, mixed>>
     */
    private function fromPeople(array $people, string $type, ?string $kind, ?string $site): array
    {
        $out = [];
        foreach ($people as $person) {
            $entity = (string) ($person['entity'] ?? 'person');
            if ($type !== 'kind' && ($entity === 'unit' || ($person['roster_only'] ?? false))) {
                continue;
            }
            if ($site !== null && strtoupper((string) ($person['site_code'] ?? '')) !== $site) {
                continue;
            }
            $presence = (string) ($person['presence'] ?? '');
            $safety = (string) ($person['safety'] ?? '');
            $match = match ($type) {
                'in' => $presence === IscPobClassifyAction::PRESENCE_IN,
                'out' => $presence === IscPobClassifyAction::PRESENCE_OUT,
                'unknown' => $presence !== IscPobClassifyAction::PRESENCE_IN && $presence !== IscPobClassifyAction::PRESENCE_OUT,
                'safe' => $presence === IscPobClassifyAction::PRESENCE_IN && $safety === IscPobClassifyAction::SAFETY_SAFE,
                'unsafe' => $presence === IscPobClassifyAction::PRESENCE_IN && $safety === IscPobClassifyAction::SAFETY_UNSAFE,
                'kind' => ($safety === IscPobClassifyAction::SAFETY_UNSAFE || ($person['from_violation'] ?? false))
                    && (string) ($person['hazard_kind'] ?? '') === (string) $kind,
                default => $presence === IscPobClassifyAction::PRESENCE_IN,
            };
            if (! $match) {
                continue;
            }
            $lat = (float) ($person['lat'] ?? 0);
            $lng = (float) ($person['lng'] ?? 0);
            $out[] = [
                'name' => (string) ($person['name'] ?? $person['sid'] ?? 'Personel'),
                'sid' => (string) ($person['sid'] ?? ''),
                'company' => $person['company'] ?? null,
                'site' => $person['site_code'] ?? $person['iupk_site'] ?? null,
                'status' => $presence !== '' ? $presence : '—',
                'safety' => $person['hazard_kind_label'] ?? ($safety !== '' ? $safety : null),
                'gps' => ($lat != 0.0 && $lng != 0.0) ? $lat.', '.$lng : 'tidak ada',
                'checked_in_at' => $person['entered_at'] ?? $person['gps_updated_at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $checkins
     * @return list<array<string, mixed>>
     */
    private function fromCheckins(array $checkins, ?string $site): array
    {
        $out = [];
        foreach ($checkins as $row) {
            if ($site !== null && strtoupper((string) ($row['site_code'] ?? '')) !== $site) {
                continue;
            }
            $out[] = [
                'name' => (string) ($row['name'] ?? $row['sid'] ?? 'Check-in'),
                'sid' => (string) ($row['sid'] ?? ''),
                'company' => $row['company'] ?? null,
                'site' => $row['site_code'] ?? $row['gate'] ?? null,
                'status' => 'rfid',
                'safety' => 'sudah check-in',
                'gps' => '—',
                'checked_in_at' => $row['checked_in_at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $rows
     * @return list<array<string, mixed>>
     */
    private function fromReconcile(mixed $rows, string $status, ?string $site): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = strtoupper((string) ($row['site_code'] ?? ''));
            if ($site !== null && $code !== '' && $code !== $site) {
                continue;
            }
            $out[] = [
                'name' => (string) ($row['name'] ?? $row['sid'] ?? 'Personel'),
                'sid' => (string) ($row['sid'] ?? ''),
                'company' => $row['company'] ?? null,
                'site' => $row['site_code'] ?? null,
                'status' => $status,
                'safety' => $status,
                'gps' => '—',
                'checked_in_at' => $row['checked_in_at'] ?? null,
            ];
        }

        return $out;
    }

    private function normalizeType(string $type, ?string $safety, ?string $kind): string
    {
        $type = strtolower(trim($type));
        if ($kind !== null && $kind !== '') {
            return 'kind';
        }
        if ($safety === 'safe' || $safety === 'unsafe') {
            return $safety;
        }
        if (! in_array($type, self::TYPES, true)) {
            return 'in';
        }

        return $type;
    }

    private function sheetTitle(string $type): string
    {
        return match ($type) {
            'in' => 'Dalam konsesi',
            'out' => 'Di luar konsesi',
            'unknown' => 'GPS tidak diketahui',
            'safe' => 'Personel safe',
            'unsafe' => 'Personel unsafe',
            'checkin' => 'Check-in RFID',
            'both' => 'RFID dan Besigma',
            'gap_br' => 'Besigma tanpa RFID',
            'gap_rb' => 'RFID tanpa Besigma',
            'current' => 'GPS aktif',
            default => 'Roster ISC',
        };
    }
}
