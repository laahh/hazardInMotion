<?php

declare(strict_types=1);

namespace App\Actions\Isc;

final class IscRfidReconcileAction
{
    /**
     * @param  list<array<string, mixed>>  $ever
     * @param  list<array<string, mixed>>  $current
     * @param  list<array<string, mixed>>  $rfid
     * @return array<string, mixed>
     */
    public function execute(array $ever, array $current, array $rfid, int $sampleLimit = 50): array
    {
        $everMap = $this->sidMap($ever);
        $currentMap = $this->sidMap($current);
        $rfidMap = $this->sidMap($rfid);

        $everSids = array_keys($everMap);
        $currentSids = array_keys($currentMap);
        $rfidSids = array_keys($rfidMap);

        $besigmaMinusRfid = array_values(array_diff($everSids, $rfidSids));
        $rfidMinusBesigma = array_values(array_diff($rfidSids, $everSids));
        $both = array_values(array_intersect($everSids, $rfidSids));

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
            'both' => $this->sample($both, $this->mergeNames($everMap, $rfidMap), $sampleLimit),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return array<string, string>
     */
    public function sidMap(array $people): array
    {
        $map = [];
        foreach ($people as $person) {
            $sid = mb_strtoupper(trim((string) ($person['sid'] ?? $person['kode_sid'] ?? '')));
            if ($sid === '') {
                continue;
            }
            $name = trim((string) ($person['name'] ?? $person['fullname'] ?? $person['nama_karyawan'] ?? ''));
            $map[$sid] = $name !== '' ? $name : $sid;
        }

        return $map;
    }

    /**
     * @param  list<string>  $sids
     * @param  array<string, string>  $names
     * @return list<array{sid:string,name:?string}>
     */
    private function sample(array $sids, array $names, int $limit): array
    {
        $out = [];
        foreach (array_slice($sids, 0, $limit) as $sid) {
            $out[] = ['sid' => $sid, 'name' => $names[$sid] ?? $sid];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $a
     * @param  array<string, string>  $b
     * @return array<string, string>
     */
    private function mergeNames(array $a, array $b): array
    {
        foreach ($b as $sid => $name) {
            if (! isset($a[$sid]) || $a[$sid] === $sid) {
                $a[$sid] = $name;
            }
        }

        return $a;
    }
}
