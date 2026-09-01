<?php

declare(strict_types=1);

namespace App\Actions\Isc;

final class IscRfidReconcileAction
{
    public const LIST_LIMIT = 400;

    /**
     * @param  list<array<string, mixed>>  $ever
     * @param  list<array<string, mixed>>  $current
     * @param  list<array<string, mixed>>  $rfid
     * @return array<string, mixed>
     */
    public function execute(array $ever, array $current, array $rfid, int $sampleLimit = self::LIST_LIMIT): array
    {
        $everMap = $this->sidRecords($ever);
        $currentMap = $this->sidRecords($current);
        $rfidMap = $this->sidRecords($rfid);

        $everSids = array_keys($everMap);
        $currentSids = array_keys($currentMap);
        $rfidSids = array_keys($rfidMap);

        $besigmaMinusRfid = array_values(array_diff($everSids, $rfidSids));
        $rfidMinusBesigma = array_values(array_diff($rfidSids, $everSids));
        $both = array_values(array_intersect($everSids, $rfidSids));
        $merged = $this->mergeRecords($everMap, $rfidMap);

        return [
            'ever_count' => count($everSids),
            'current_count' => count($currentSids),
            'rfid_count' => count($rfidSids),
            'gap_besigma_minus_rfid_count' => count($besigmaMinusRfid),
            'gap_rfid_minus_besigma_count' => count($rfidMinusBesigma),
            'both_count' => count($both),
            'ever' => $everSids,
            'current' => $currentSids,
            'rfid' => $rfidSids,
            'gap_besigma_minus_rfid' => $this->sample($besigmaMinusRfid, $everMap, $sampleLimit),
            'gap_rfid_minus_besigma' => $this->sample($rfidMinusBesigma, $rfidMap, $sampleLimit),
            'both' => $this->sample($both, $merged, $sampleLimit),
            'current_list' => $this->sample($currentSids, $currentMap, $sampleLimit),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return array<string, string>
     */
    public function sidMap(array $people): array
    {
        $map = [];
        foreach ($this->sidRecords($people) as $sid => $row) {
            $map[$sid] = (string) ($row['name'] ?? $sid);
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return array<string, array<string, mixed>>
     */
    private function sidRecords(array $people): array
    {
        $map = [];
        foreach ($people as $person) {
            $sid = mb_strtoupper(trim((string) ($person['sid'] ?? $person['kode_sid'] ?? '')));
            if ($sid === '') {
                continue;
            }
            $name = trim((string) ($person['name'] ?? $person['fullname'] ?? $person['nama_karyawan'] ?? ''));
            $map[$sid] = [
                'sid' => $sid,
                'name' => $name !== '' ? $name : $sid,
                'company' => $this->nullable($person['company'] ?? null),
                'job_title' => $this->nullable($person['job_title'] ?? null),
                'site_code' => $this->nullable($person['site_code'] ?? $person['gate'] ?? null),
                'checked_in_at' => $this->nullable($person['checked_in_at'] ?? null),
            ];
        }

        return $map;
    }

    /**
     * @param  list<string>  $sids
     * @param  array<string, array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function sample(array $sids, array $records, int $limit): array
    {
        $out = [];
        foreach (array_slice($sids, 0, max(0, $limit)) as $sid) {
            $out[] = $records[$sid] ?? ['sid' => $sid, 'name' => $sid, 'company' => null];
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $a
     * @param  array<string, array<string, mixed>>  $b
     * @return array<string, array<string, mixed>>
     */
    private function mergeRecords(array $a, array $b): array
    {
        foreach ($b as $sid => $row) {
            if (! isset($a[$sid])) {
                $a[$sid] = $row;
                continue;
            }
            if (($a[$sid]['name'] ?? $sid) === $sid && ($row['name'] ?? '') !== '') {
                $a[$sid]['name'] = $row['name'];
            }
            if (($a[$sid]['company'] ?? null) === null) {
                $a[$sid]['company'] = $row['company'] ?? null;
            }
            if (($a[$sid]['checked_in_at'] ?? null) === null) {
                $a[$sid]['checked_in_at'] = $row['checked_in_at'] ?? null;
            }
        }

        return $a;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
