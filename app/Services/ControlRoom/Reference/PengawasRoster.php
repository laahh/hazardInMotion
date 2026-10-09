<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Reference;

use App\Enums\ControlRoomSiteCode;

/**
 * Daftar pengawas Control Room dari config/control-room-pengawas.php.
 * Pengawas tidak punya jadwal shift — dianggap bertugas setiap hari.
 */
final class PengawasRoster
{
    /**
     * @return list<array{sid: string, name: string, site: ControlRoomSiteCode}>
     */
    public function all(): array
    {
        $people = [];
        foreach ((array) config('control-room-pengawas.list', []) as $entry) {
            [$sid, $site, $name] = array_pad(array_values((array) $entry), 3, '');
            $sid = strtoupper(trim((string) $sid));
            $siteCode = ControlRoomSiteCode::tryFrom(strtoupper(trim((string) $site)));
            $name = trim((string) $name);
            if ($sid === '' || $name === '' || $siteCode === null || isset($people[$sid])) {
                continue;
            }
            $people[$sid] = ['sid' => $sid, 'name' => $name, 'site' => $siteCode];
        }

        $people = array_values($people);
        usort($people, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $people;
    }

    /**
     * @return list<array{sid: string, name: string, site: ControlRoomSiteCode}>
     */
    public function forSite(?ControlRoomSiteCode $site): array
    {
        if ($site === null) {
            return $this->all();
        }

        return array_values(array_filter(
            $this->all(),
            static fn (array $person): bool => $person['site'] === $site,
        ));
    }

    /**
     * Site yang punya pengawas, urut sesuai enum.
     *
     * @return list<ControlRoomSiteCode>
     */
    public function sites(): array
    {
        $used = [];
        foreach ($this->all() as $person) {
            $used[$person['site']->value] = true;
        }

        return array_values(array_filter(
            ControlRoomSiteCode::cases(),
            static fn (ControlRoomSiteCode $site): bool => isset($used[$site->value]),
        ));
    }

    public function has(string $sid): bool
    {
        $sid = strtoupper(trim($sid));
        foreach ($this->all() as $person) {
            if ($person['sid'] === $sid) {
                return true;
            }
        }

        return false;
    }
}
