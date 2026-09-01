<?php

declare(strict_types=1);

namespace App\Services\Isc;

/**
 * Dataset dummy untuk preview peta/PIC sebelum GPS, Besigma, dan RFID live tersedia.
 * Posisi orang dihitung ulang vs IUPK + polygon bahaya dummy (bukan angka hardcode In/Out).
 */
final class IscPobDemoDataset
{
    /**
     * @return list<array<string, mixed>>
     */
    public function people(): array
    {
        $fresh = now()->subMinutes(3)->toDateTimeString();
        $stale = now()->subMinutes(40)->toDateTimeString();

        return [
            $this->person('sid:BC001', 'BC001', 'Andi Pratama', 'PT Berau Coal', 'Operator Hauling', 'BMO', 1.950, 117.300, $fresh),
            $this->person('sid:BC002', 'BC002', 'Budi Santoso', 'PT Pamapersada', 'Pengawas Pit', 'BMO', 1.990, 117.385, $fresh),
            $this->person('sid:BC003', 'BC003', 'Citra Lestari', 'PT Darma Henwa', 'Surveyor', 'LMO', 2.180, 117.620, $fresh),
            $this->person('sid:BC004', 'BC004', 'Dedi Kurniawan', 'PT Berau Coal', 'Mechanic', 'BMO', 1.940, 117.280, $stale),
            $this->person('sid:BC005', 'BC005', 'Eka Wijaya', 'PT BUMA', 'Operator Excavator', 'BMO', 1.930, 117.250, $fresh),
            $this->person('sid:BC006', 'BC006', 'Farah Ningsih', 'PT Berau Coal', 'HSE Officer', 'BMO', 1.988, 117.390, $fresh),
        ];
    }

    /**
     * Polygon bahaya dummy di dalam konsesi BMO 2.
     *
     * @return list<array<string, mixed>>
     */
    public function hazardFeatures(): array
    {
        return [
            [
                'type' => 'Feature',
                'properties' => [
                    'id' => 'demo-pit-blast',
                    'name' => 'Zona Peledakan Pit BMO',
                    'aktivitas' => 'Blasting',
                    'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
                    'hazard_kind_label' => IscHazardBoundaryClassifier::KIND_LABELS[IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER],
                    'risk_name' => 'tinggi',
                    'risk_color' => '#c5221f',
                ],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [117.370, 1.978],
                        [117.405, 1.978],
                        [117.405, 2.002],
                        [117.370, 2.002],
                        [117.370, 1.978],
                    ]],
                ],
            ],
            [
                'type' => 'Feature',
                'properties' => [
                    'id' => 'demo-kompetensi-bmo',
                    'name' => 'Zona Kompetensi Hauling BMO',
                    'kategori' => 'kompetensi',
                    'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE,
                    'hazard_kind_label' => IscHazardBoundaryClassifier::KIND_LABELS[IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE],
                    'risk_color' => '#e37400',
                ],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [117.288, 1.942],
                        [117.312, 1.942],
                        [117.312, 1.958],
                        [117.288, 1.958],
                        [117.288, 1.942],
                    ]],
                ],
            ],
            [
                'type' => 'Feature',
                'properties' => [
                    'id' => 'demo-unit-bmo',
                    'name' => 'Zona Bahaya Unit Pit BMO',
                    'kategori' => 'bahaya unit',
                    'hazard_kind' => IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
                    'hazard_kind_label' => IscHazardBoundaryClassifier::KIND_LABELS[IscHazardBoundaryClassifier::KIND_UNIT_DANGER],
                    'risk_color' => '#7627bb',
                ],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [117.238, 1.922],
                        [117.262, 1.922],
                        [117.262, 1.938],
                        [117.238, 1.938],
                        [117.238, 1.922],
                    ]],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function everIdentifiers(): array
    {
        $base = [];
        foreach ($this->people() as $person) {
            $base[] = [
                'key' => $person['key'],
                'user_id' => $person['user_id'],
                'sid' => $person['sid'],
                'nik' => $person['nik'],
                'name' => $person['name'],
                'company' => $person['company'],
                'job_title' => $person['job_title'],
            ];
        }

        $base[] = [
            'key' => 'sid:BC007',
            'user_id' => '7',
            'sid' => 'BC007',
            'nik' => '6472000000000007',
            'name' => 'Gilang Putra',
            'company' => 'PT Berau Coal',
            'job_title' => 'Admin Site',
        ];
        $base[] = [
            'key' => 'sid:BC008',
            'user_id' => '8',
            'sid' => 'BC008',
            'nik' => '6472000000000008',
            'name' => 'Hana Sari',
            'company' => 'PT BUMA',
            'job_title' => 'Dispatcher',
        ];

        return $base;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rfidOnsite(): array
    {
        return [
            ['sid' => 'BC001', 'name' => 'Andi Pratama', 'company' => 'PT Berau Coal', 'gate' => 'Gate BMO', 'checked_in_at' => now()->subHours(4)->toDateTimeString(), 'checked_out_at' => null],
            ['sid' => 'BC002', 'name' => 'Budi Santoso', 'company' => 'PT Pamapersada', 'gate' => 'Gate BMO', 'checked_in_at' => now()->subHours(3)->toDateTimeString(), 'checked_out_at' => null],
            ['sid' => 'BC005', 'name' => 'Eka Wijaya', 'company' => 'PT BUMA', 'gate' => 'Gate BMO', 'checked_in_at' => now()->subHours(5)->toDateTimeString(), 'checked_out_at' => null],
            ['sid' => 'BC006', 'name' => 'Farah Ningsih', 'company' => 'PT Berau Coal', 'gate' => 'Gate BMO', 'checked_in_at' => now()->subHours(2)->toDateTimeString(), 'checked_out_at' => null],
            ['sid' => 'RFID09', 'name' => 'Irfan Malik', 'company' => 'PT Mitra', 'gate' => 'Gate LMO', 'checked_in_at' => now()->subHours(1)->toDateTimeString(), 'checked_out_at' => null],
            ['sid' => 'RFID10', 'name' => 'Joko Widodo Site', 'company' => 'PT Kontraktor', 'gate' => 'Gate GMO', 'checked_in_at' => now()->subHours(6)->toDateTimeString(), 'checked_out_at' => null],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(): array
    {
        return [
            [
                'id' => 9001,
                'person_key' => 'sid:BC002',
                'sid' => 'BC002',
                'name' => 'Budi Santoso',
                'company' => 'PT Pamapersada',
                'job_title' => 'Pengawas Pit',
                'lat' => 1.990,
                'lng' => 117.385,
                'iupk_site' => 'Site Binungan 2',
                'hazard_boundary_id' => 'demo-pit-blast',
                'hazard_name' => 'Zona Peledakan Pit BMO',
                'entered_at' => now()->subMinutes(42)->toIso8601String(),
                'exited_at' => null,
                'duration_seconds' => 42 * 60,
                'status' => 'open',
                'rule_code' => 'hazard-entry',
                'interventions' => [],
            ],
            [
                'id' => 9002,
                'person_key' => 'sid:BC006',
                'sid' => 'BC006',
                'name' => 'Farah Ningsih',
                'company' => 'PT Berau Coal',
                'job_title' => 'HSE Officer',
                'lat' => 1.988,
                'lng' => 117.390,
                'iupk_site' => 'Site Binungan 2',
                'hazard_boundary_id' => 'demo-pit-blast',
                'hazard_name' => 'Zona Peledakan Pit BMO',
                'entered_at' => now()->subHours(2)->toIso8601String(),
                'exited_at' => null,
                'duration_seconds' => 2 * 3600,
                'status' => 'in_progress',
                'rule_code' => 'hazard-entry',
                'interventions' => [[
                    'id' => 8001,
                    'type' => 'himbauan',
                    'notes' => 'Himbauan keluar zona blasting, dampingan radio channel 3.',
                    'status' => 'submitted',
                    'pic_name' => 'PIC Demo',
                    'created_at' => now()->subMinutes(50)->toIso8601String(),
                    'evidences' => [['original_name' => 'foto-himbauan.jpg', 'path' => '#']],
                    'verification' => null,
                ]],
            ],
            [
                'id' => 9003,
                'person_key' => 'sid:BC001',
                'sid' => 'BC001',
                'name' => 'Andi Pratama',
                'company' => 'PT Berau Coal',
                'job_title' => 'Operator Hauling',
                'lat' => 1.950,
                'lng' => 117.300,
                'iupk_site' => 'Site Binungan 2',
                'hazard_boundary_id' => 'demo-pit-blast',
                'hazard_name' => 'Zona Peledakan Pit BMO',
                'entered_at' => now()->subDay()->toIso8601String(),
                'exited_at' => now()->subDay()->addMinutes(18)->toIso8601String(),
                'duration_seconds' => 18 * 60,
                'status' => 'closed',
                'rule_code' => 'hazard-entry',
                'interventions' => [[
                    'id' => 8002,
                    'type' => 'evakuasi',
                    'notes' => 'Evakuasi ke safe point SP-2.',
                    'status' => 'verified',
                    'pic_name' => 'PIC Demo',
                    'created_at' => now()->subDay()->addMinutes(5)->toIso8601String(),
                    'evidences' => [['original_name' => 'evakuasi.pdf', 'path' => '#']],
                    'verification' => [
                        'result' => 'verified',
                        'verifier_name' => 'Verifier Demo',
                        'notes' => 'Evidence lengkap.',
                    ],
                ]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function postEventReport(string $from, string $to): array
    {
        return [
            'ready' => true,
            'demo' => true,
            'from' => $from,
            'to' => $to,
            'totals' => [
                'events' => 3,
                'duration_seconds' => (42 * 60) + (2 * 3600) + (18 * 60),
                'open' => 1,
                'in_progress' => 1,
                'closed' => 1,
                'verified' => 1,
                'repeat_people' => 1,
            ],
            'by_status' => [
                ['key' => 'open', 'count' => 1],
                ['key' => 'in_progress', 'count' => 1],
                ['key' => 'closed', 'count' => 1],
            ],
            'by_site' => [
                ['key' => 'Site Binungan 2', 'count' => 3],
            ],
            'by_company' => [
                ['key' => 'PT Pamapersada', 'count' => 1],
                ['key' => 'PT Berau Coal', 'count' => 2],
            ],
            'repeat_offenders' => [
                ['person_key' => 'sid:BC002', 'name' => 'Budi Santoso', 'sid' => 'BC002', 'company' => 'PT Pamapersada', 'count' => 2],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function person(
        string $key,
        string $sid,
        string $name,
        string $company,
        string $job,
        string $site,
        float $lat,
        float $lng,
        string $updatedAt,
    ): array {
        return [
            'key' => $key,
            'user_id' => substr($sid, -1),
            'sid' => $sid,
            'nik' => '6472'.str_pad(substr($sid, -3), 12, '0', STR_PAD_LEFT),
            'npk' => $sid,
            'name' => $name,
            'company' => $company,
            'job_title' => $job,
            'division' => 'Operasi',
            'site' => $site,
            'lat' => $lat,
            'lng' => $lng,
            'gps_updated_at' => $updatedAt,
        ];
    }
}
