<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Models\Isc\IscHazardReport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar laporan hazard (isc_hazard_reports) untuk HUD peta ISC:
 * - "ter-intervensi": laporan yang sudah punya intervention_id.
 * - "historical": seluruh laporan yang pernah masuk, status terkini.
 *
 * Status yang ditampilkan = kolom isc_hazard_reports.status apa adanya
 * (saat ini selalu 'submitted' / "Submit"). Status event/intervensi terkait
 * tetap disertakan sebagai info tambahan (event_status/intervention_status),
 * tapi tidak dipakai sebagai status utama/badge.
 */
final class IscHazardReportBoardService
{
    public const LIST_LIMIT = 300;

    /**
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'submitted' => 'Submit',
        'open' => 'Open',
        'in_progress' => 'On progress',
        'closed' => 'Selesai',
        'verified' => 'Terverifikasi',
        'rejected' => 'Ditolak',
    ];

    /**
     * @return array<string, mixed>
     */
    public function intervenedPayload(): array
    {
        return $this->payload(fn (Builder $query): Builder => $query->whereNotNull('intervention_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function historicalPayload(): array
    {
        return $this->payload();
    }

    /**
     * @param  (callable(Builder): Builder)|null  $scope
     * @return array<string, mixed>
     */
    private function payload(?callable $scope = null): array
    {
        $query = IscHazardReport::query()
            ->with(['intervention', 'event'])
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT);

        if ($scope !== null) {
            $query = $scope($query);
        }

        $reports = [];
        $byStatus = [];
        foreach ($query->get() as $report) {
            $status = $this->deriveStatus($report);
            $byStatus[$status['status']] = ($byStatus[$status['status']] ?? 0) + 1;
            $reports[] = $this->toArray($report, $status);
        }

        return [
            'ready' => true,
            'status_labels' => self::STATUS_LABELS,
            'summary' => [
                'total' => count($reports),
                'by_status' => $byStatus,
            ],
            'reports' => $reports,
        ];
    }

    /**
     * @return array{status:string, status_label:string, event_status:?string, intervention_status:?string}
     */
    private function deriveStatus(IscHazardReport $report): array
    {
        $eventStatus = $report->event?->status;
        $interventionStatus = $report->intervention?->status;
        $primary = $report->status ?: 'submitted';

        return [
            'status' => $primary,
            'status_label' => self::STATUS_LABELS[$primary] ?? ucfirst(str_replace('_', ' ', $primary)),
            'event_status' => $eventStatus,
            'intervention_status' => $interventionStatus,
        ];
    }

    /**
     * @param  array{status:string, status_label:string, event_status:?string, intervention_status:?string}  $status
     * @return array<string, mixed>
     */
    private function toArray(IscHazardReport $report, array $status): array
    {
        $lat = $report->event?->lat;
        $lng = $report->event?->lng;
        $hasPoint = $lat !== null && $lng !== null && (float) $lat != 0.0 && (float) $lng != 0.0;

        return [
            'id' => $report->id,
            'event_id' => $report->event_id,
            'intervention_id' => $report->intervention_id,
            'status' => $status['status'],
            'status_label' => $status['status_label'],
            'event_status' => $status['event_status'],
            'intervention_status' => $status['intervention_status'],
            'intervention_type' => $report->intervention?->type,
            'lat' => $hasPoint ? (float) $lat : null,
            'lng' => $hasPoint ? (float) $lng : null,
            'has_point' => $hasPoint,
            'show_url' => $report->event_id ? route('isc.interventions.show', $report->event_id) : null,
            // Orang yang MELANGGAR (dari event Besigma terkait) — ini yang
            // dipakai sebagai nama utama di kartu, BUKAN nama_pelapor.
            'violator_name' => $report->event?->name,
            'violator_sid' => $report->event?->sid,
            'violator_company' => $report->event?->company,
            'perusahaan' => $report->perusahaan,
            'site' => $report->site,
            'lokasi' => $report->lokasi,
            'detail_lokasi' => $report->detail_lokasi,
            'nama_pelapor' => $report->nama_pelapor,
            'jabatan_pelapor' => $report->jabatan_pelapor,
            'sid_pelapor' => $report->sid_pelapor,
            'pic_nama' => $report->pic_nama,
            'pic_jabatan' => $report->pic_jabatan,
            'is_observasi_area_kritis' => (bool) $report->is_observasi_area_kritis,
            'ketidaksesuaian' => $report->ketidaksesuaian,
            'sub_ketidaksesuaian' => $report->sub_ketidaksesuaian,
            'quick_action' => $report->quick_action,
            'deskripsi_temuan' => $report->deskripsi_temuan,
            'created_at' => $report->created_at?->toIso8601String(),
        ];
    }
}
