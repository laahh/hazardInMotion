<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DailyOperationPlan;
use App\Models\SupervisoryCriticalAreaAlertLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SupervisoryCriticalAreaAlertLogSeeder extends Seeder
{
    /**
     * Dummy alert Critical Area Juni 2026: belum ada observasi, semua sudah diintervensi.
     * Terkait DOP area kritis (daily_operation_plans).
     *
     * @var array<int, array<string, mixed>>
     */
    private const DOP_TEMPLATES = [
        [
            'id' => 1668,
            'pekerjaan' => 'Simpang 4 Hauling Hamparan',
            'unit_id' => 'BMO 3',
            'perusahaan' => 'PT BAR',
            'lokasi' => 'Area Kritis',
            'detail_lokasi' => 'Simpang 4 Hauling Hamparan',
            'tanggal' => '2026-06-19',
        ],
        [
            'id' => 1667,
            'pekerjaan' => 'Barging batu bara di jetty',
            'unit_id' => 'BMO 3',
            'perusahaan' => 'PT BAR',
            'lokasi' => 'Area Kritis',
            'detail_lokasi' => 'Jetty Hamparan',
            'tanggal' => '2026-06-19',
        ],
        [
            'id' => 1666,
            'pekerjaan' => 'Simpang 4 Hauling Hamparan',
            'unit_id' => 'BMO 3',
            'perusahaan' => 'PT BAR',
            'lokasi' => 'Area Kritis',
            'detail_lokasi' => 'Simpang 4 Hauling Hamparan',
            'tanggal' => '2026-06-18',
        ],
        [
            'id' => 1665,
            'pekerjaan' => 'Barging batu bara di jetty',
            'unit_id' => 'BMO 3',
            'perusahaan' => 'PT BAR',
            'lokasi' => 'Area Kritis',
            'detail_lokasi' => 'Jetty BBA',
            'tanggal' => '2026-06-18',
        ],
        [
            'id' => 1664,
            'pekerjaan' => 'Barging batu bara di jetty',
            'unit_id' => 'BMO 3',
            'perusahaan' => 'PT BAR',
            'lokasi' => 'Area Kritis',
            'detail_lokasi' => 'Jetty Hamparan',
            'tanggal' => '2026-06-18',
        ],
    ];

    public function run(): void
    {
        $dopIds = $this->ensureDopRecords();

        $start = Carbon::create(2026, 6, 1);
        $end = Carbon::create(2026, 6, 30);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $tanggal = $date->format('Y-m-d');

            foreach (self::DOP_TEMPLATES as $template) {
                $dopId = $dopIds[$template['id']];

                $snapshot = [
                    'id' => $dopId,
                    'pekerjaan' => $template['pekerjaan'],
                    'unit_id' => $template['unit_id'],
                    'perusahaan' => $template['perusahaan'],
                    'lokasi' => $template['lokasi'],
                    'detail_lokasi' => $template['detail_lokasi'],
                    'tanggal_dop' => $tanggal,
                ];

                SupervisoryCriticalAreaAlertLog::updateOrCreate(
                    [
                        'tanggal' => $tanggal,
                        'dop_id' => $dopId,
                    ],
                    [
                        'has_observasi' => false,
                        'status_intervensi' => SupervisoryCriticalAreaAlertLog::STATUS_SUDAH_DI_INTERVENSI,
                        'temuan' => 'Belum ada observasi',
                        'dop_snapshot' => $snapshot,
                    ]
                );
            }
        }
    }

    /**
     * Pastikan 5 DOP template ada di daily_operation_plans, kembalikan map id template => id aktual.
     *
     * @return array<int, int>
     */
    private function ensureDopRecords(): array
    {
        $map = [];

        foreach (self::DOP_TEMPLATES as $template) {
            $targetId = (int) $template['id'];
            $payload = [
                'pekerjaan' => $template['pekerjaan'],
                'unit_id' => $template['unit_id'],
                'perusahaan' => $template['perusahaan'],
                'lokasi' => $template['lokasi'],
                'detail_lokasi' => $template['detail_lokasi'],
                'tanggal' => $template['tanggal'],
                'status' => true,
            ];

            $existing = DailyOperationPlan::find($targetId);
            if ($existing) {
                $existing->update($payload);
                $map[$targetId] = $existing->id;
                continue;
            }

            $byKey = DailyOperationPlan::query()
                ->where('pekerjaan', $template['pekerjaan'])
                ->where('detail_lokasi', $template['detail_lokasi'])
                ->where('unit_id', $template['unit_id'])
                ->whereDate('tanggal', $template['tanggal'])
                ->first();

            if ($byKey) {
                $map[$targetId] = $byKey->id;
                continue;
            }

            $created = DailyOperationPlan::create($payload);
            $map[$targetId] = $created->id;
        }

        return $map;
    }
}
